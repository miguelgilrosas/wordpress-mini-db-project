<?php

namespace WpMiniDb\Entity;

abstract class BaseEntity extends PreBaseEntity
{
	protected $tableName;

    /** @var ?string */
    protected static $staticTableFullName = null;
    /** @var ?string */
    protected static $charsetCollate = null;

    function __construct(array $fieldList, string $childClass)
    {
        self::setTableName();
        parent::__construct(self::$staticTableFullName, $childClass);
        $this->fields->names = $fieldList;
    }

    private function setTableName(): void
    {
        if (!self::$staticTableFullName) {
            global $wpdb;
            self::$staticTableFullName = $wpdb->prefix . $this->tableName;
            self::$charsetCollate = $wpdb->get_charset_collate();
        }
    }
}
