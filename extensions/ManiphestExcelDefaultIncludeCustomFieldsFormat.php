<?php

/**
 * This is largely based off ManiphestExcelDefaultFormat with a few changes:
 * 1. (All) Custom Fields are included in the output.
 * 2. To accommodate having custom fields, the way headers were organized has
 *   been changed from individual arrays for each cell 'metadata' to instead be
 *   a single array of cell 'metadata' objects.
 * 3. Description moved to last cell as it's typically the lengthiest.
 * 4. This was developed ad-hoc/as-needed in the moment so coding habits were
 *   favored over proper php coding guidelines (I use Java daily not PHP)
 *   and is also the reason for the following warnings..
 *
 * IMPORTANT: The current manner of getting custom fields was implemented with
 *   no regard to proper usage - ex: fiddling with the proxy to get field data.
 *   It was not clear what the appropriate manner for retrieving this data is
 *   so it is more likely to break with upgrades to Phabricator.
 *
 *   The only custom field types tested are the following:
 *    - PhabricatorStandardCustomFieldInt
 *    - PhabricatorStandardCustomFieldSelect
 *
 *   Another issues is that *_NO_* policy-checking is done on the fields - 
 *   It wasn't clear whether this was the case, but PhabricatorCustomField
 *   does seem to have some methods for setting/requiring a "viewer".
 *   This was not needed in the moment so it's ignored for the time being.
 */

final class ManiphestExcelDefaultIncludeCustomFieldsFormat extends ManiphestExcelFormat {
  public function getName() {
    return pht('Default with Custom Fields');
  }

  public function getFileName() {
    return 'maniphest_tasks_'.date('Ymd');
  }

  public function buildWorkbook(
    PHPExcel $workbook,
    array $tasks,
    array $handles,
    PhabricatorUser $user) {

    $sheet = $workbook->setActiveSheetIndex(0);
    $sheet->setTitle(pht('Tasks'));

    // Header Cell
    // title => the displayed header title in the spreadsheet, in row 0
    // width => initial width in pixels for the column, null leaves unspecified
    // celltype => which format the column data should be set as, default is STRING
    //   can be null if it's a date field
    // isDate => there is no date format in the PHPExcel_Cell_DataType, so this is needed
    // cftype => the custom field data type, only specified for custom field headers

    $colHeaders = array(
      array(
        'title' => pht('ID'),
        'width' => null,
        'celltype' => PHPExcel_Cell_DataType::TYPE_STRING,
        'isDate' => false,
      ),
      array(
        'title' => pht('Owner'),
        'width' => 15,
        'celltype' => PHPExcel_Cell_DataType::TYPE_STRING,
        'isDate' => false,
      ),
      array(
        'title' => pht('Status'),
        'width' => null,
        'celltype' => PHPExcel_Cell_DataType::TYPE_STRING,
        'isDate' => false,
      ),
      array(
        'title' => pht('Priority'),
        'width' => 10,
        'celltype' => PHPExcel_Cell_DataType::TYPE_STRING,
        'isDate' => false,
      ),
      array(
        'title' => pht('Date Created'),
        'width' => 15,
        'celltype' => null,
        'isDate' => true,
      ),
      array(
        'title' => pht('Date Updated'),
        'width' => 15,
        'celltype' => null,
        'isDate' => true,
      ),
      array(
        'title' => pht('Title'),
        'width' => 60,
        'celltype' => PHPExcel_Cell_DataType::TYPE_STRING,
        'isDate' => false,
      ),
      array(
        'title' => pht('Projects'),
        'width' => 20,
        'celltype' => PHPExcel_Cell_DataType::TYPE_STRING,
        'isDate' => false,
      ),
      array(
        'title' => pht('Columns'),
        'width' => 20,
        'celltype' => PHPExcel_Cell_DataType::TYPE_STRING,
        'isDate' => false,
      ),
      array(
        'title' => pht('URI'),
        'width' => 30,
        'celltype' => PHPExcel_Cell_DataType::TYPE_STRING,
        'isDate' => false,
      ),
    );

    // Create the custom fields from their configured definition and extract header cell details
    $customFields = id(new ManiphestConfiguredCustomField())->createFields(null);
    $customFieldsHeaderMap = array();
    foreach ($customFields as $customField) {
      // Using the proxy I think is needed due to using ManiphestConfiguredCustomField.createFields
      // That seems to be wrapping the PhabricatorStandardCustomField, but doesn't proxy/delegate the
      // getFieldType() method which is needed here.
      $fieldName = $customField->getProxy()->getFieldName();
      $fieldType = $customField->getProxy()->getFieldType();

      $isDateField = $fieldType == 'date';
      $cellType = PHPExcel_Cell_DataType::TYPE_STRING;
      if ($fieldType == 'int') {
        $cellType = PHPExcel_Cell_DataType::TYPE_NUMERIC;
      }

      $customFieldHeader = array(
        'title' => $fieldName,
        'width' => null,
        'celltype' => $cellType,
        'isDate' => $isDateField,
        'cftype' => $fieldType,
      );
      $customFieldsHeaderMap[$fieldName] = $customFieldHeader;
      $colHeaders[] = $customFieldHeader;
    }

    $colHeaders[] = array(
      'title' => pht('Description'),
      'width' => 100,
      'celltype' => PHPExcel_Cell_DataType::TYPE_STRING,
      'isDate' => false,
    );

    $status_map = ManiphestTaskStatus::getTaskStatusMap();
    $pri_map = ManiphestTaskPriority::getTaskPriorityMap();

    $header_format = array(
      'font'  => array(
        'bold' => true,
      ),
    );

    $rows = array();

    $headerRow = array();
    foreach ($colHeaders as $colIdx => $column) {
    $headerRow[] = $column['title'];
    }
    $rows[] = $headerRow;

    $project_ids_used = array();
    foreach ($tasks as $task) {
      foreach ($task->getProjectPHIDs() as $phid) {
        $project_ids_used[] = $phid;
      }
    }
    $project_ids_used = array_unique($project_ids_used);

    $task_to_column = array();
    if (count($project_ids_used) > 0) {
      $colquery = id(new PhabricatorProjectColumnQuery())
        ->setViewer($user)
        ->withProjectPHIDs($project_ids_used)
        ->execute();
      $columns = mpull($colquery, null, 'getPHID');

      if (count($columns) == 0) {
        break;
      }

      $column_ids = mpull($columns, 'getPHID');
      $task_ids = mpull($tasks, 'getPHID');
      foreach ($task_ids as $task_id) {
        foreach ($column_ids as $column_id) {
          $ppositions = id(new PhabricatorProjectColumnPositionQuery())
             ->setViewer($user)
             ->withObjectPHIDs(array($task_id))
             ->withColumnPHIDs(array($column_id))
             ->execute();
           $ppositions = mpull($ppositions, null, 'getObjectPHID');

           foreach ($ppositions as $pposition) {
             $pposition->attachColumn($columns[$column_id]);
             if (empty($task_to_column[$task_id])) {
               $task_id_to_column[$task_id] = array();
             }
             $task_to_column[$task_id][] = $pposition;
           }
        }
      }
    }

    foreach ($tasks as $task) {
      $task_owner = null;
      if ($task->getOwnerPHID()) {
        $task_owner = $handles[$task->getOwnerPHID()]->getName();
      }

      $projects = array();
      $project_columns = array();
      foreach ($task->getProjectPHIDs() as $phid) {
        $projects[] = $handles[$phid]->getName();
      }
      $projects = implode(', ', $projects);

      $pcolumn_names = array();
      $task_ppositions = $task_to_column[$task->getPHID()];
      foreach ($task_ppositions as $task_position) {
        $pcolumn_names[] = $task_position->getColumn()->getDisplayName();
      }
      $pcolumn_names = implode(', ', $pcolumn_names);

      $row = array(
        'T'.$task->getID(),
        $task_owner,
        idx($status_map, $task->getStatus(), '?'),
        idx($pri_map, $task->getPriority(), '?'),
        $this->computeExcelDate($task->getDateCreated()),
        $this->computeExcelDate($task->getDateModified()),
        $task->getTitle(),
        $projects,
        $pcolumn_names,
        PhabricatorEnv::getProductionURI('/T'.$task->getID()),
      );

      // Query for the custom fields for a specific maniphest task object
      $taskCustomFields = PhabricatorCustomField::getObjectFields($task, PhabricatorCustomField::ROLE_DEFAULT);
      $taskCustomFields->readFieldsFromStorage($task);
      $taskCustomFieldsMap = array();
      foreach ($taskCustomFields->getFields() as $customField) {
        $fieldName = $customField->getFieldName();
        $taskCustomFieldsMap[$fieldName] = $customField;
      }

      // We want to order the items from the task custom field object in the same order
      // which the custom field headers exist in the $colHeaders array
      // So loop over the header map, and pull the populated custom field from the populated map
      foreach ($customFieldsHeaderMap as $fieldName => $customFieldHeader) {
        $customField = $taskCustomFieldsMap[$fieldName];
        if ($customField == null) {
          $row[] = null;
          continue;
        }

        $fieldValue = $customField->getProxy()->getFieldValue();

        // option/select-style custom fields have values which are actually the identifier from json spec
        // lookup the display value to be used from the 'getOptions()' on the PhabricatorStandardCustomFieldSelect
        if ($fieldValue !== null && $customFieldHeader['cftype'] == 'select') {
          $options = $customField->getProxy()->getOptions();
          $fieldValue = $options[$fieldValue];
        }
        $row[] = $fieldValue;
      }

      $row[] = id(new PhutilUTF8StringTruncator())
        ->setMaximumBytes(512)
        ->truncateString($task->getDescription());

      $rows[] = $row;
    }

    foreach ($rows as $row => $cols) {
      foreach ($cols as $col => $spec) {
        $cell_name = $this->col($col).($row + 1);
        $cell = $sheet
          ->setCellValue($cell_name, $spec, $return_cell = true);

        // If the header row only apply the bold-style and width, but do not 
        // apply the date-format/data-type since the values will always be string
        if ($row == 0) {
          $sheet->getStyle($cell_name)->applyFromArray($header_format);

          $width = $colHeaders[$col]['width'];
          if ($width !== null) {
            $sheet->getColumnDimension($this->col($col))->setWidth($width);
          }
        } else {
          $is_date = $colHeaders[$col]['isDate'];
          if ($is_date) {
            $code = PHPExcel_Style_NumberFormat::FORMAT_DATE_YYYYMMDD2;
            $sheet
              ->getStyle($cell_name)
              ->getNumberFormat()
              ->setFormatCode($code);
          } else {
            $cellType = $colHeaders[$col]['celltype'];
            if ($cellType == null) {
              $cellType = PHPExcel_Cell_DataType::TYPE_STRING;
            }
            $cell->setDataType($cellType);
          }
        }
      }
    }
  }

  private function col($n) {
    return chr(ord('A') + $n);
  }
}
