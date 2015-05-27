<?php

namespace rock\mongodb;


use rock\events\Event;

class AfterSaveEvent extends Event
{
    /**
     * @var array The attribute values that had changed and were saved.
     */
    public $changedAttributes;
} 