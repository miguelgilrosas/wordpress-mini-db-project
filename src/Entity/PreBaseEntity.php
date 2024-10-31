<?php

namespace WpMiniDb\Entity;

abstract class PreBaseEntity
{
    /** @var \stdClass */
    protected $fields;
    /** @var ?string */
    protected $tableFullName;
    /** @var bool */
    protected $isNewRow;
    /** string */
    private $childClass;

    protected function __construct(string $tableFullName, string $childClass)
    {
        $this->childClass = $childClass;
        $this->fields = new \stdClass();
        $this->fields->names = [];
        $this->fields->values = [];
        $this->fields->changed = [];
        $this->tableFullName = $tableFullName;
        $this->isNewRow = true;
    }

    /**
     * @param $id string | int
     * @return ?self
     */
    function getByKey($id): ?self
    {
        $quote = is_int($id) ? '' : "'";
        global $wpdb;
        $sql = "select * from $this->tableFullName where " . $this->fields->names[0] . " = $quote$id$quote;";
        $result = $wpdb->get_results($wpdb->prepare($sql, []), ARRAY_A);

        if (null === $result || (is_array($result) && !count($result))) {
            return null;
        }

        foreach ($result[0] as $fieldName => $fieldValue) {
            $value = null;
            switch (gettype($fieldValue)) {
                case 'integer':
                    $value = filter_var($fieldValue, FILTER_SANITIZE_NUMBER_INT);
                    break;

                case 'double':
                    $value = filter_var($fieldValue, FILTER_SANITIZE_NUMBER_FLOAT);
                    break;

                case 'NULL':
                    break;

                default:
                    $value = filter_var($fieldValue, FILTER_SANITIZE_STRING);
            }

            $this->fields->values[$fieldName] = $value;
        }

        $this->isNewRow = false;
        return $this;
    }

    /**
     * @param $whereList array [[$field1, '=', $value1], [$field2, '>', $value2]], operators can be >, <, =, !=, 'like' and 'not like'
     * @return PreBaseEntity[] Collection of Entities (child class)
     */
    function getByFields(array $whereList): array
    {
        /** @var PreBaseEntity[] $rows */
        $rows = [];
        $firstLoop = true;
        $sql = "select * from $this->tableFullName where ";
        foreach ($whereList as $where) {
            if (!$firstLoop) {
                $sql .= 'and ';
            }
            $firstLoop = false;
            $quote = is_int($where[2]) ? '' : "'";
            $sql .= $where[0] . ' ' . $where[1] . ' ' . $quote . $where[2] . $quote . ' ';
        }

        //        var_dump($sql);
        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare($sql, []), ARRAY_A);
        if (null === $results) {
            return $rows;
        }

        foreach ($results as $result) {
            /** @var self $entity */
            $entity = new $this->childClass;
            foreach ($result as $fieldName => $fieldValue) {
                $value = null;
                switch (gettype($fieldValue)) {
                    case 'integer':
                        $value = filter_var($fieldValue, FILTER_SANITIZE_NUMBER_INT);
                        break;

                    case 'double':
                        $value = filter_var($fieldValue, FILTER_SANITIZE_NUMBER_FLOAT);
                        break;

                    case 'NULL':
                        break;

                    default:
                        $value = filter_var($fieldValue, FILTER_SANITIZE_STRING);
                }

                $entity->setField($fieldName, $value);
            }
            $entity->isNewRow = false;
            $rows[] = $entity;
        }

        return $rows;
    }

    /**
     * @param string $fieldName
     * @return mixed
     */
    function getField(string $fieldName)
    {
        return $this->fields->values[$fieldName];
    }

    /**
     * @param $fieldName string
     * @param $fieldValue mixed
     * @return ?self
     */
    function setField(string $fieldName, $fieldValue): ?self
    {
        if (!$this->isNewRow && $fieldName === $this->fields->names[0]) {
            return null;
        }

        $this->fields->values[$fieldName] = $fieldValue;
        if (!in_array($fieldName, $this->fields->changed, true)) {
            $this->fields->changed[] = $fieldName;
        }

        return $this;
    }

    function save(): ?self
    {
        global $wpdb;

        $fieldsList = [];
        $placeholders = [];
        $values = [];

        foreach ($this->fields->changed as $field) {
            if (!$this->isNewRow && $field === $this->fields->names[0]) {
                return null;
            }
            $fieldsList[] = $field;
            $placeholders[] = is_int($this->fields->values[$field]) ? '%d' : '%s';
            $values[] = $this->fields->values[$field];
        }

        if ($this->isNewRow) {
            $sql = "INSERT INTO $this->tableFullName (" . implode(',', $fieldsList) . ") VALUES (" . implode(',', $placeholders) . ")";
        } else {

            $sql = "UPDATE $this->tableFullName SET ";
            $setClauses = [];
            for ($idx = 0, $len = count($fieldsList); $idx < $len; ++$idx) {
                $setClauses[] = $fieldsList[$idx] . '=' . $placeholders[$idx];
            }
            $sql .= implode(',', $setClauses);
            $primaryKey = $this->fields->names[0];
            $primaryKeyPlaceholder = is_int($this->fields->values[$primaryKey]) ? '%d' : '%s';
            $sql .= " WHERE $primaryKey = $primaryKeyPlaceholder";
            $values[] = $this->fields->values[$primaryKey];
        }

        $wpdb->query($wpdb->prepare($sql, $values));

        if ($this->isNewRow) {
            $this->setField($this->fields->names[0], $wpdb->insert_id);
        }

        $this->isNewRow = false;
        $this->fields->changed = [];

        return $this;
    }

    function delete(): PreBaseEntity
    {
        if (!$this->isNewRow) {
            $quote = is_int($this->getField($this->fields->names[0])) ? '' : "'";
            $sql = "
                delete from $this->tableFullName
                where " . $this->fields->names[0] . " = $quote" . $this->fields->values[$this->fields->names[0]] . "$quote
            ";

            global $wpdb;
            $wpdb->query($wpdb->prepare($sql, []));
            $this->isNewRow = true;
        }

        return $this;
    }
}
