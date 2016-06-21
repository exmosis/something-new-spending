<?php

$callback = null;
if (isset($_GET['callback'])) {
  $callback = trim($_GET['callback']);
}

$csv_url = 'https://somethingnewuk.github.io/finances/national/expenditure.csv';
$csv_fields = array(
	'total_paid' => 6,
	'date' => 8,
	'supplier' => 2,
);

$raw_data = getCsv($csv_url, $csv_fields, 1);

// Not used yet, but intention to allow supplemental metadata for suppliers
$suppliers = buildSuppliers($raw_data, 'supplier');

// ===== ANNUAL TOTALS ===== //

$annual_total = aggregateRawData($raw_data, 
					'total_paid',
					array(
						array(
							'convertDateToYear',
							'date'
						)
					)
				);

// ===== ANNUAL TOTALS FOR EACH SUPPLIER ===== //

$annual_total_by_supplier = aggregateRawData($raw_data, 
						'total_paid',
						array(
							array(
								'convertDateToYear',
								'date'
							),
							array( 
								'convertSupplier',
								'supplier'
							)
						)
					);

outputJson(array(
		'annual_totals' => $annual_total,
		'annual_supplier_totals' => $annual_total_by_supplier
	),
  $callback 
);


function outputJson($body_fields_and_data, $callback = null) {
	$json_data = json_encode($body_fields_and_data);
  if ($callback) {
    $json_data = $callback . '(' . $json_data . ');';
  }

  if ($callback) {
    header('Content-type: application/javascript');
  } else {
  	header('Content-type: application/json');
  }
  echo $json_data;
}

function convertSupplier($supplier) {
	return $supplier;
}

function convertDateToYear($date) {
	$date_info = date_parse($date);
	return $date_info['year'];
}


/**
 * Function to aggregate data according to any number of callbacks.
 * The $aggregate_field_name field within $raw_data will be summed (no average yet), broken down
 * according to the callback configs given. Each callback item should be an array of 
 * (function name, field_for_arg1, field_for_arg2, ...)
 */
function aggregateRawData($raw_data, $aggregate_field_name, array $aggregation_point_configs) {

	$return_data = array();

	foreach ($raw_data as $row) {
		if (! array_key_exists($aggregate_field_name, $row)) {
			echo "Error: aggregate field name " . $aggregate_field_name . " not set in row:\n";
			print_r($row);
			exit;
		}

		$row_value = $row[$aggregate_field_name];

		$key_index = array(); // store the chain of keys to store the breakdown agg value in

		foreach ($aggregation_point_configs as $agg) {
			$agg_function = array_shift($agg);

			// Check here if agg_function exists or not

			$agg_args = array();
			foreach ($agg as $agg_field) {

				// TODO: Check if field exists

				$agg_args[] = $row[$agg_field];
			}

			$key_index[] = call_user_func_array($agg_function, $agg_args);
		}

		// now loop through key indexes to see if they exist in return_data already
		$check_point_ref =& $return_data;
		foreach ($key_index as $next_key) {
			if (! array_key_exists($next_key, $check_point_ref)) {
				$check_point_ref[$next_key] = array();
			}
			$check_point_ref =& $check_point_ref[$next_key];
		}
		// $check_point_ref shoud now be the point in the array we want to store the value. it will be an array by default though. Reset to 0.
		if (is_array($check_point_ref)) {
			$check_point_ref = 0;
		}


		$check_point_ref += $row_value;
	} // next row

	return $return_data;

}


/**
 * Take in an array of keyed arrays and return an array of unique supplier names
 */
function buildSuppliers($raw_data, $supplier_field) {
	
	$return_data = array();
	foreach ($raw_data as $row) {

		$supplier = $row[$supplier_field];

		if (! in_array($supplier, $return_data)) {
			// Not there, add to our array
			$return_data[] = $supplier;
		}
	}

	return $return_data;
}

/**
 * Fetch content from server, and return requested data fields as a keyed array.
 */
function getCsv($csv_url, $fields_to_return, $rows_to_skip) {

	$row = 1;
	$return_data = array();

	if (($handle = fopen($csv_url, "r")) !== FALSE) {
	    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {

        	$num = count($data);
        	if ($row++ <= $rows_to_skip) {
			continue;
		}

		$row_return_data = array();

		foreach ($fields_to_return as $field => $field_index) {
			if ($field_index < $num) {
				$row_return_data[$field] = trim($data[$field_index]);
			} else {
				echo 'Error: Not enough fields to reach field index ' . $field_index;
				exit;
			}
		}

		$return_data[] = $row_return_data;

	    }
    	    fclose($handle);
	}

	return $return_data;
}

