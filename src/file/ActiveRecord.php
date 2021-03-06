<?php
namespace rock\mongodb\file;

use rock\file\UploadedFile;
use rock\mongodb\MongoException;

/**
 * ActiveRecord is the base class for classes representing Mongo GridFS files in terms of objects.
 *
 * To specify source file use the `file` attribute. It can be specified in one of the following ways:
 *  - string - full name of the file, which content should be stored in GridFS
 *  - \rock\file\UploadedFile - uploaded file instance, which content should be stored in GridFS
 *
 * For example:
 *
 * ```php
 * $record = new ImageFile();
 * $record->file = '/path/to/some/file.jpg';
 * $record->save();
 * ```
 *
 * You can also specify file content via `newFileContent` attribute:
 *
 * ```php
 * $record = new ImageFile();
 * $record->newFileContent = 'New file content';
 * $record->save();
 * ```
 *
 * Note: `newFileContent` always takes precedence over `file`.
 *
 * @property null|string $fileContent File content. This property is read-only.
 * @property resource $fileResource File stream resource. This property is read-only.
 *
 */
abstract class ActiveRecord extends \rock\mongodb\ActiveRecord
{
    /**
     * @inheritdoc
     * @return ActiveQuery the newly created {@see \rock\mongodb\file\ActiveQuery} instance.
     */
    public static function find()
    {
        return new ActiveQuery(get_called_class());
    }

    /**
     * Return the Mongo GridFS collection instance for this AR class.
     * @return Collection collection instance.
     */
    public static function getCollection()
    {
        return static::getConnection()->getFileCollection(static::collectionName());
    }

    /**
     * Returns the list of all attribute names of the model.
     * This method could be overridden by child classes to define available attributes.
     * Note: all attributes defined in base Active Record class should be always present
     * in returned array.
     * For example:
     * ```php
     * public function attributes()
     * {
     *     return array_merge(
     *         parent::attributes(),
     *         ['tags', 'status']
     *     );
     * }
     * ```
     * @return array list of attribute names.
     */
    public function attributes()
    {
        return [
            '_id',
            'filename',
            'uploadDate',
            'length',
            'chunkSize',
            'md5',
            'file',
            'newFileContent'
        ];
    }

    /**
     * @inheritdoc
     * @see ActiveRecord::insert()
     */
    protected function insertInternal(array $attributes = null)
    {
        if (!$this->beforeSave(true)) {
            return false;
        }
        $values = $this->getDirtyAttributes($attributes);
        if (empty($values)) {
            $currentAttributes = $this->getAttributes();
            foreach ($this->primaryKey() as $key) {
                $values[$key] = isset($currentAttributes[$key]) ? $currentAttributes[$key] : null;
            }
        }
        $collection = static::getCollection();
        if (isset($values['newFileContent'])) {
            $newFileContent = $values['newFileContent'];
            unset($values['newFileContent']);
        }
        if (isset($values['file'])) {
            $newFile = $values['file'];
            unset($values['file']);
        }
        if (isset($newFileContent)) {
            $newId = $collection->insertFileContent($newFileContent, $values);
        } elseif (isset($newFile)) {
            $fileName = $this->extractFileName($newFile);
            $newId = $collection->insertFile($fileName, $values);
        } else {
            $newId = $collection->insert($values);
        }
        $this->setAttribute('_id', $newId);
        $values['_id'] = $newId;

        $changedAttributes = array_fill_keys(array_keys($values), null);
        $this->setOldAttributes($values);
        $this->afterSave(true, $changedAttributes);

        return true;
    }

    /**
     * @inheritdoc
     * @see ActiveRecord::update()
     * @throws MongoException
     */
    protected function updateInternal(array $attributes = null)
    {
        if (!$this->beforeSave(false)) {
            return false;
        }
        $values = $this->getDirtyAttributes($attributes);
        if (empty($values)) {
            $this->afterSave(false, $values);
            return 0;
        }

        $collection = static::getCollection();
        if (isset($values['newFileContent'])) {
            $newFileContent = $values['newFileContent'];
            unset($values['newFileContent']);
        }
        if (isset($values['file'])) {
            $newFile = $values['file'];
            unset($values['file']);
        }
        if (isset($newFileContent) || isset($newFile)) {
            $fileAssociatedAttributeNames = [
                'filename',
                'uploadDate',
                'length',
                'chunkSize',
                'md5',
                'file',
                'newFileContent'
            ];
            $values = array_merge($this->getAttributes([], $fileAssociatedAttributeNames), $values);
            $rows = $this->deleteInternal();
            $insertValues = $values;
            $insertValues['_id'] = $this->getAttribute('_id');
            if (isset($newFileContent)) {
                $collection->insertFileContent($newFileContent, $insertValues);
            } else {
                $fileName = $this->extractFileName($newFile);
                $collection->insertFile($fileName, $insertValues);
            }
            $this->setAttribute('newFileContent', null);
            $this->setAttribute('file', null);
        } else {
            $condition = $this->getOldPrimaryKey(true);
            $lock = $this->optimisticLock();
            if ($lock !== null) {
                if (!isset($values[$lock])) {
                    $values[$lock] = $this->$lock + 1;
                }
                $condition[$lock] = $this->$lock;
            }
            // We do not check the return value of update() because it's possible
            // that it doesn't change anything and thus returns 0.
            $rows = $collection->update($condition, $values);
            if ($lock !== null && !$rows) {
                throw new MongoException('The object being updated is outdated.');
            }
        }

        $changedAttributes = [];
        foreach ($values as $name => $value) {
            $changedAttributes[$name] = $this->getOldAttribute($name);
            $this->setOldAttribute($name, $value);
        }
        $this->afterSave(false, $changedAttributes);

        return $rows;
    }

    /**
     * Extracts filename from given raw file value.
     *
     * @param mixed $file raw file value.
     * @return string file name.
     * @throws MongoException on invalid file value.
     */
    protected function extractFileName($file)
    {
        if ($file instanceof UploadedFile) {
            return $file->tempName;
        } elseif (is_string($file)) {
            if (file_exists($file)) {
                return $file;
            } else {
                throw new MongoException("File '{$file}' does not exist.");
            }
        } else {
            throw new MongoException('Unsupported type of "file" attribute.');
        }
    }

    /**
     * Refreshes the `file` attribute from file collection, using current primary key.
     * @return \MongoGridFSFile|null refreshed file value.
     */
    public function refreshFile()
    {
        $mongoFile = $this->getCollection()->get($this->getPrimaryKey());
        $this->setAttribute('file', $mongoFile);

        return $mongoFile;
    }

    /**
     * Returns the associated file content.
     *
     * @return null|string file content.
     * @throws MongoException on invalid file attribute value.
     */
    public function getFileContent()
    {
        $file = $this->getAttribute('file');
        if (empty($file) && !$this->getIsNewRecord()) {
            $file = $this->refreshFile();
        }
        if (empty($file)) {
            return null;
        } elseif ($file instanceof \MongoGridFSFile) {
            $fileSize = $file->getSize();
            if (empty($fileSize)) {
                return null;
            } else {
                return $file->getBytes();
            }
        } elseif ($file instanceof UploadedFile) {
            return file_get_contents($file->tempName);
        } elseif (is_string($file)) {
            if (file_exists($file)) {
                return file_get_contents($file);
            } else {
                throw new MongoException("File '{$file}' does not exist.");
            }
        } else {
            throw new MongoException('Unsupported type of "file" attribute.');
        }
    }

    /**
     * Writes the the internal file content into the given filename.
     *
     * @param string $filename full filename to be written.
     * @return boolean whether the operation was successful.
     * @throws MongoException on invalid file attribute value.
     */
    public function writeFile($filename)
    {
        $file = $this->getAttribute('file');
        if (empty($file) && !$this->getIsNewRecord()) {
            $file = $this->refreshFile();
        }
        if (empty($file)) {
            throw new MongoException('There is no file associated with this object.');
        } elseif ($file instanceof \MongoGridFSFile) {
            return ($file->write($filename) == $file->getSize());
        } elseif ($file instanceof UploadedFile) {
            return copy($file->tempName, $filename);
        } elseif (is_string($file)) {
            if (file_exists($file)) {
                return copy($file, $filename);
            } else {
                throw new MongoException("File '{$file}' does not exist.");
            }
        } else {
            throw new MongoException('Unsupported type of "file" attribute.');
        }
    }

    /**
     * This method returns a stream resource that can be used with all file functions in PHP,
     * which deal with reading files. The contents of the file are pulled out of MongoDB on the fly,
     * so that the whole file does not have to be loaded into memory first.
     *
     * @return resource file stream resource.
     * @throws MongoException on invalid file attribute value.
     */
    public function getFileResource()
    {
        $file = $this->getAttribute('file');
        if (empty($file) && !$this->getIsNewRecord()) {
            $file = $this->refreshFile();
        }
        if (empty($file)) {
            throw new MongoException('There is no file associated with this object.');
        } elseif ($file instanceof \MongoGridFSFile) {
            return $file->getResource();
        } elseif ($file instanceof UploadedFile) {
            return fopen($file->tempName, 'r');
        } elseif (is_string($file)) {
            if (file_exists($file)) {
                return fopen($file, 'r');
            } else {
                throw new MongoException("File '{$file}' does not exist.");
            }
        } else {
            throw new MongoException('Unsupported type of "file" attribute.');
        }
    }
}
