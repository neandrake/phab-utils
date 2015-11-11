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
 *   Another issues is that *_NO_* policy-checking is done on the fields - 
 *   It wasn't clear whether this was the case, but PhabricatorCustomField
 *   does seem to have some methods for setting/requiring a "viewer".
 *   This was not needed in the moment so it's ignored for the time being.
 */

final class ManiphestExcelDefaultIncludeCustomFieldsFormat extends ManiphestExcelFormat {
  public function getName() {
    return pht('Deafult with Custom Fields');
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
      	'title' => pht('URI'),
      	'width' => 30,
      	'celltype' => PHPExcel_Cell_DataType::TYPE_STRING,
      	'isDate' => false,
      ),
    );

    $customFields = id(new ManiphestConfiguredCustomField())->createFields(null);
    $customFieldsMap = array();
    foreach ($customFields as $customField) {
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
    	$customFieldsMap[$fieldName] = $customFieldHeader;
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

    foreach ($tasks as $task) {
      $task_owner = null;
      if ($task->getOwnerPHID()) {
        $task_owner = $handles[$task->getOwnerPHID()]->getName();
      }

      $projects = array();
      foreach ($task->getProjectPHIDs() as $phid) {
        $projects[] = $handles[$phid]->getName();
      }
      $projects = implode(', ', $projects);

      $row = array(
        'T'.$task->getID(),
        $task_owner,
        idx($status_map, $task->getStatus(), '?'),
        idx($pri_map, $task->getPriority(), '?'),
        $this->computeExcelDate($task->getDateCreated()),
        $this->computeExcelDate($task->getDateModified()),
        $task->getTitle(),
        $projects,
        PhabricatorEnv::getProductionURI('/T'.$task->getID()),
      );

      $taskCustomFields = PhabricatorCustomField::getObjectFields($task, PhabricatorCustomField::ROLE_DEFAULT);
      $taskCustomFields->readFieldsFromStorage($task);
      $taskCustomFieldsMap = array();
      foreach ($taskCustomFields->getFields() as $customField) {
      	$fieldName = $customField->getFieldName();
      	$taskCustomFieldsMap[$fieldName] = $customField;
      }
      // loop over the task's custom fields in the same order they appear in the header
      foreach ($customFieldsMap as $fieldName => $customFieldHeader) {
      	$customField = $taskCustomFieldsMap[$fieldName];
      	if ($customField == null) {
      		$row[] = null;
      		continue;
      	}

      	$fieldValue = $customField->getProxy()->getFieldValue();
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
