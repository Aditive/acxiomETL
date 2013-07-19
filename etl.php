#!/usr/bin/php

<?php

//include_once("./mappings.php");




function main() {

	$first  = new DateTime('NOW');



	
	$mapFilePath = "./data/acxiom_data_map.csv";

	$dataFilePath = "./data/Aditive_Loreal_Test.csv";

	$outFilePath = "./data/MERGED.csv";

	$mapArr =  processMap($mapFilePath);
	// see mapArr.log to see waht $mapArr looks like when var_dump()'d.

	$headersArr =  processData($dataFilePath);
	// see headersArr.log to see waht $headersArr looks like when var_dump()'d.

	
	$dataArr =  mergeArrays($dataFilePath, $outFilePath, $headersArr, $mapArr);

	writeOutData($dataArr, $outFilePath);

	$second = new DateTime('NOW');

	$diff = $first->diff( $second );

	printf("TIME:\n");
	print_r($diff);

}

function categorizeHeader($headerName) {

	

	$outArr = array(
		"k"	=> null,
		"v"	=> null
		);

	//TODO: move to other file
	$matches = array(

		// search for => replace with
		"/ibe(\d*)_{1}(Personicx|IBE)_{1}/" => "",
		"/(DataQuick|Premier)_{1}/" => "",
		"/OneZero/"	=> ""

		);


	foreach ($matches as $key => $value) {

		$headerName = preg_replace($key, $value, $headerName);

	}

	//printf("[categorizeHeader] - ALIVE.  HEADER:[%s]\n", $headerName);


	// now that we've trimmed it up, we have some details to take care of

	// explode on the _
	$headerName = explode("_", $headerName);


	switch($headerName[0]) {

		case("AdultAgeRanges"): {
			// 			0 		1		2	3 	4	5
			//AdultAgeRanges_Unknown_Gender_65_TO_74

			//			0 		1 	 2  	3 	4
			//AdultAgeRanges_Unknown_Gender_75_PLUS

			//			0 		1  2  3
			//AdultAgeRanges_Males_75_PLUS

			$outArr["k"] = $headerName[0];	//TODo:some substitution stuff here


			if($headerName[1] == "Unknown") {

				$gender = $headerName[1];

				$ageRange = array_slice ($headerName, 3, 5);

				//echo("AGE: UNKNOW GENDER HERE IS AGE RANGE:{" . implode("-", $ageRange) . "}\n" );
				//var_dump($ageRange);
				

			} else {

				$gender = $headerName[1];
				$ageRange = array_slice ($headerName, 2, 5);

				//echo("AGE: KNOWN GENDER " . $gender . " HERE IS AGE RANGE:{".implode("-", $ageRange)."}\n");
				//var_dump($ageRange);
				
			}

			$outArr["v"] = ($gender . "," . implode("-", $ageRange));

			// printf("AGE - ADULT: \n");
			// var_dump($outArr);

			return $outArr;

		}


		case("ChildrensAgeRanges"): {

			// 			0 		1	2	3 	4	5
			//ChildrensAgeRanges_Age_06_TO_10_Unknown_Gender

			$outArr["k"] = $headerName[0];	//TODO:some substitution stuff here


			// cut from the 02 position (06 in example above) through the 5th.
			$ageRange = array_slice ($headerName, 2, 3);

			if(count($ageRange) == 5) {

				// pull out UNKNOWN
				$gender = $headerName[5];
			} else {

				// pull out Male or Female
				$gender = $headerName[5];
			}

			$outArr["v"] = ($gender . "," . implode("-", $ageRange));

			// printf("AGE - CHILDREN: \n");
			// var_dump($outArr);

			return $outArr;

		}

		case("CreditCardIndicator"): {


			$outArr["k"] = "FINANCE";		//TODO:some substitution stuff here



			if($headerName[1] == "Bank"){
				$outArr["v"] = "Bank Card Holder";
			} else {
				// cut from the 01 position through position 3
				$cardType = array_slice ($headerName, 1, 3);

				$cardType = implode("-", $cardType);

				if(strpos($cardType, "-Card-Holder")){
					$cardType = str_replace("-Card-Holder", " Card Holder", $cardType);	
				} else {
					$cardType = $cardType. " Card Holder";
				}

				

				$outArr["v"] = $cardType;
				
			}

			return $outArr;

		}



		default: {

			// the default is to give the full name that we started with after trimming things (basicaly, re-emplode)
			// the value is null, so our caller knows to sub-in the value direct from the cell (or do other processing!)
			$outArr["k"] = implode("_", $headerName);
			$outArr["v"] = null;

			return $outArr;
		}

	}


}


function sanitizeHeader($headerName) {

	$pattern = '/\*+/';

	/*

		header names come in all sorts of different formats.  fucking a.

		so we remove every dash and space.

		they both get replaced with '_' to match the headings in the data file
	*/


	$out = str_replace("-", "*", $headerName);

	$out = str_replace(" ", "*", $out);

	//				 pattern, replacement, subject
	$out = preg_replace($pattern, "_",$out);

	return $out;

}


function processMap($mapFilePath) {

	printf("[processMap]: ALIVE!\n");


	$file_handle = fopen($mapFilePath, "r");

	// here's where we'll all store crap
	$outArr = array();


	// iterative stuff
	$currentElement = '';
	$line = "";

	$count = 0;
	$currentElement = 0;
	$prevElement = 0;

	$elementArrIndex = 0;

	$recordTemplate = array(
		"name" 		=> null,		// what is the name of the attribute
		"data"		=> array(),		// what key/values do we have for attribute?
		"default"	=> NULL 		// what is the default value?
	);


	/*
		go through each row.
	*/
	while (!feof($file_handle) ) {

		// keep track of how many records we've processes
		$count++;

		/*	
			each line has the format:

				ELEMENT, KEY, VALUE, FORMAT, LENGTH, COMMENT
				0		1 	  2		3 		4		5

		*/
		
		$line = fgetcsv($file_handle, 1024, "\t");


		/*
			if the value of the ELEMENT cell is NOT NULL, then we're getting a new element number!
		*/
		if($line[0] != '') {

			/*
				keep track of previous and current element to know 
					when we're dealing with a new element or when we're dealing with a new 'attribute' for the same element we've been dealing with
			*/

			$prevElement = $currentElement;
			$currentElement = $line[0];

			if($prevElement == $currentElement) {
				$elementArrIndex++;
			} else {
				// new element!
				$elementArrIndex = 0;
			}
			

			// get a cleaned up version of the header
			$name = sanitizeHeader($line[1]);
			$recordTemplate["name"] = $name;
			$recordTemplate["data"]["type"] = $line[3];
			$recordTemplate["data"]["length"] = $line[4];


			// we create a row with a key as the element number
			// the element number will pull up another array with all the names that are asociated with that element
			// we default to null, because some records don't have multiple K = V pairs
			// if there are muliple ones, we'll deal with it by exploding


			// every element needs an array
			if(array_key_exists($currentElement, $outArr) == false){
				$outArr[$currentElement] = array();
			}

			// every element needs a sub array for the various name / data[] collections
			if(array_key_exists($elementArrIndex, $outArr[$currentElement]) == false) {
				$outArr[$currentElement][$elementArrIndex] = array();
			}


			//check if there's already data in $elementArrIndex.  If there is, we need to go to a new one.
			// if there isn't any, we need to copy in the name, data[] 'template'
			if( $outArr[$currentElement][$elementArrIndex] == null) {

				//printf("\$outArr[\$currentElement(%s)][\$elementArrIndex(%s)) IS NULL\n", $currentElement , $elementArrIndex );
				$outArr[$currentElement][$elementArrIndex] = $recordTemplate;
			} 


		} else if($line[0] == "") {

			//baby steps
			//continue;

			/*	
				if there is no value in the ELEMENT column, then we're on a record that is a continuation

				this means that we need to take KEY column, and if we can explode it on the ' = ' symbol, then 
				there is data that we can use.

				we take that data and turn it into something useful.
				the KEY column turns into a 
				KEY and VALUE columns.

			*/
			$keyValArray = explode(" = ", $line[1]);

			if(count($keyValArray) == 2) {

				// DEBUGGING
				//printf("ELEMENT: %s, KEY: %s, VAL: %s\n", $currentElement, $keyValArray[0], $keyValArray[1]);	

				$line[2] = $keyValArray[1];
				$line[1] = $keyValArray[0];

				$outArr[$currentElement][$elementArrIndex]["data"][$line[1]] = $line[2];
				

			} else {

				/*	
					we were not able to explode on the ' = ' so we're probably on a line like "Default is Blank(s)"

					If we can, let's learn what the default value is.

						see if the cell contains Default is string,
							if it does, cut it out, trim white space and then look at the remaining value to see if it matches a known blank value

				*/

				if(preg_match("/Default is /", $line[1] )) {

					// remove the Default is
					$line[1] = preg_replace("/Default is /", '', $line[1]);
					$line[1] = trim($line[1]);


					/*
						the human string is left
						machine string is right.
					*/
					$defaults = array(
						"Blank(s)" 	=> "",
						"0"			=> "0"
					);


					
					$pos = array_search($line[1], array_keys($defaults));

					if($pos !== FALSE) {

						// OK, we know the position in the $defaults array.


						//printf("[processMap] - ELEMENT: %s, VAL: %s is IN defaults at pos: %s with machine view of [%s]\n", $currentElement, $line[1], $pos, $defaults[array_keys($defaults)[$pos] ] );
						//$recordTemplate["default"] = $defaults[array_keys($defaults)[$pos] ];
						$outArr[$currentElement][$elementArrIndex]["default"] = $defaults[array_keys($defaults)[$pos] ];
					} else {
						printf("[processMap] - VAL: %s is NOT IN defaults!\n", $line[1]);
					}
				}
				
				continue;

			}

		}

	}

	fclose($file_handle);

	printf("processMap: processed %s records!\n", $count);

	return $outArr;

}

function processData($dataFilePath) {

	printf("[processData] - ALIVE!\n");

	$headerKey = 'ibe';

	// we also write out the file... just in case
	$file_handle = fopen($dataFilePath, "r");
	
	// here's where we'll all store crap
	$outArr = array();


	// iterative stuff
	$currentElement = '';
	$type = "";
	$line = "";

	$count = 0;

	$headers = null;

	$headers = null;
	$count = 0;



	$count++;
	$line = fgetcsv($file_handle, 102400, "\t");
		
	/*
	there are **hundreds** of columns
	*/
	if($headers == null) {
		$headers = array();

		foreach ($line as $header) {

			//echo("looking at: " . $header . "\n");

			// remove any instance of 'ibe'
			$out = str_replace($headerKey, "", $header);
			$tmp = explode("_", $out);

			// if the header is a simple case, no need to sanatize... just store the 'ibe'-less header!
			// otherwise, sanatize and store.
			if( (count($tmp) == 2 ) && ($tmp[0] == $tmp[1]) ) { 

				// put the adjsuted header name back into the header array!
				//echo("STORING 1  : " . $tmp[0] . "\n");
				$headers[$tmp[0]] = null;

			} else {
				//echo("STORING 2  : " . sanitizeHeader( implode($tmp) ) . "\n");

				/*
					we don't have a simple case... aka, one attribute ID, but at least 2 'attributes' for this ID

					so what we do is store the 'names' as an array
					in order to do this, we must check if the headers[$numericTestID] is null or is an array.  if it's an array, push.
					it null, make an array, add
				*/


				//					$tmp[0]	$tmp[1] $tmp[2]
				// need to cut off the 8601_IBE_Premier_
				$tmp1 = array_slice($tmp, 3);
				$tmp1 = implode("_", $tmp1);

				if(array_key_exists($tmp[0], $headers) == false){
					$headers[$tmp[0]] = array( sanitizeHeader($tmp1) );
				} else {
					array_push($headers[$tmp[0]], sanitizeHeader($tmp1) );
				}

			}

		}
	}

	printf("processData: processed %s records!\n", $count);
	return $headers;
		
}

function mergeArrays($dataPath, $outputPath, $headdersArray, $mapArr) {

	printf("[mergeArrays] - ALIVE!\n");

	// we also write out the file... just in case
	$file_handle = fopen($dataPath, "r");

	// here's where we'll all store crap
	$outArr = array();

	// keep track of how much we've processed
	$count = 0;

	$currentEmail = NULL;

	$headers = null;

	while (!feof($file_handle) ) {
	//while ($count <= 1000) {

		$count++;
		
		$line = fgetcsv($file_handle, 10240, "\t");
		$index = 0;

		// dont process line 1; it's the headers
		if($count === 1) {
			$headers = $line;
			continue;
		}

		foreach ($headdersArray as $key => $val) {

			// are we on the EMAIL case?  If so, we need to start examining a new row
			if($key === "EMAIL") {
				$index = 0;
				$row = array();
				$currentEmail = $line[$index];
				$index++;
				continue;
			}


			if( gettype($val) == "array") {

				$arrLen = count($val);

				// we need to count the number of things associated with the array and adjust the index
				// we need to UNWIND the array
				//printf("examining header: %s - IS AN ARRAY with length %s\n", $key, $arrLen);


				$elementArrIndex = 0;
				foreach ($val as $elementNumber => $elementName) {

					// figure out what the default is for this header
					$default = $mapArr[$key][$elementArrIndex]["default"];

					//printf("EXAMING \$mapArr at: KEY: [%s], elementArrIndex: [%s], elementNumber: [%s] HEADER:[%s]\n", $key, $elementArrIndex, $elementNumber, $headers[$index + $elementNumber]);
					//var_dump($mapArr[$key][$elementArrIndex]);
					//var_dump($mapArr[$key][$elementNumber]);

					// baby steps
					//continue;


					// if the cell contains teh default value, we do NOTHING.
					if($line[$index+$elementNumber] === $default){
						continue;
					} else {
						//printf("INDEX: %s, examining header: %s, DEFAULT:[%s], elementNumber %s, VALUE: %s \n", ($index + $elementArrIndex), $key, $default, $elementNumber, $line[$index+$elementNumber]);	
					}

				 	if(count($mapArr[$key][$elementNumber]["data"]) == 0) {
 
				 		if($line[$index + $elementNumber] != "") {

				 			//printf("{1} INDEX: %s, elementArrIndex: %s, KEY: %s (%s) VALUE: %s (%s)\n",  $index, $elementArrIndex, $key, 
							//$mapArr[$key][$elementArrIndex]["name"] , $line[$index + $elementNumber], $line[$index + $elementNumber]);


							$row["p"] = $mapArr[$key][$elementArrIndex]["name"];
							$row["v"] = $line[$index+$elementNumber];
				 		}

						// now we have the property / value, we need to add the email hash
						$row['email'] = $currentEmail;

						// add all three to the output
						$outArr[] = $row;


					} else {

						if($line[$index + $elementNumber] != "") {

							if( array_key_exists($line[$index + $elementNumber], $mapArr[$key][$elementNumber]["data"])){
								// printf("{2.1} INDEX: %s, elementArrIndex: %s, KEY: %s (%s) VAL: %s (%s) HEDER: [%s]\n"
								// 	,$index
								// 	,$elementArrIndex
								// 	,$key
								// 	,$mapArr[$key][$elementNumber]["name"]		// human readable from MAP
								// 	,$line[$index + $elementNumber] 
								// 	,$mapArr[$key][$elementNumber]["data"][ $line[$index + $elementNumber] ]
								// 	,$headers[$index + $elementNumber]

								// );

								//$row["p"] = $mapArr[$key][$elementArrIndex]["name"];
								$tmp = categorizeHeader($headers[$index + $elementNumber]);

								if($tmp["v"] === null) {

									$row["p"] = $tmp["k"];
									$row["v"] = $mapArr[$key][$elementNumber]["data"][ $line[$index + $elementNumber] ];

								} else {
									$row["p"] = $tmp["k"];
									$row["v"] = $tmp["v"];
								}

								
							} else {
								// printf("{2.2} INDEX: %s, elementArrIndex: %s, KEY: %s (%s) VAL: %s (%s)\n",  $index, $elementArrIndex
								// 	,$key
								// 	,$mapArr[$key][$elementNumber]["name"]
								// 	,$line[$index + $elementNumber] 
								// 	,$line[$index + $elementNumber]
								// );

								$row["p"] = $mapArr[$key][$elementNumber]["name"];
								$row["v"] = $line[$index + $elementNumber];
							}
							
						}

						// now we have the property / value, we need to add the email hash
						$row['email'] = $currentEmail;

						// add all three to the output
						$outArr[] = $row;

						//var_dump($row);


					}

					$elementArrIndex++;
					//$index++;

				 }

				
				$index = ($index + ($arrLen - 1));
				

			} else {

				//BABY STEPS
				// $index++;
				// continue;

				// there is data at cell $index for the line.
				if($line[$index] != "") {

					/*	

						if the 'data' array has NO values, then we assume we just COPY directly from the line.

					*/

					// every data array has **at least** two elements (the format and length attribute)
					if(count($mapArr[$key][0]["data"]) == 2) {

						//printf("{G1}INDEX: %s, KEY: %s (%s) VALUE: %s (%s)\n",  $index, $key, $mapArr[$key][0]["name"] , $line[$index], $line[$index]);
						$row["p"] = $mapArr[$key][0]["name"];
						$row["v"] = $line[$index];

					} else {

						// the DATA element has a few things for us.  this is good.
						/*
							the problem is that the data array probably has something like:

							08 = 08 some moderate value


							but the actuall data has:

							8

							8 !== 08 in PHP.

							we must take 8, turn it to a string, and make sure it's the correct length!
						*/



						//TODO: sanity checks; these SHOULD NOT EVER BE NULL!!!!
						$format = $mapArr[$key][0]["data"]["type"];
						$length = $mapArr[$key][0]["data"]["length"];

						// printf("{G2}INDEX: %s, KEY: %s (%s) VALUE: %s TYPE:[%s] LENGTH:[%s]  HDR:[%s]\n"
						// 	,$index
						// 	,$key
						// 	,$mapArr[$key][0]["name"]
						// 	,$line[$index]
						// 	,$format
						// 	,$length
						// 	,$headers[$index]
						// 	);


						if($format === "CHARACTER"){

							//printf("{G2.1 - type is character}\n");

							// no padding needed
							if(strlen($line[$index]) == $length) {

								//printf("{G2.1 - LENGTH MATCHES}\n");

							} else {
								//printf("{G2.1 - LENGTH NO MATCH.  HAVE: %s NEED: %s}\n", strlen($line[$index]), $length);


								$line[$index] = str_pad($line[$index], $length, "0", STR_PAD_LEFT);

								//printf("{G2.1 - LENGTH NO MATCH NOW HAVE: %s NEED: %s}\n", strlen($line[$index]), $length);
							}

						} else {
							//printf("{G2.1 - type is NOT CHARACTER}\n");
						}


						// $index++;
						// continue;


						//printf("{G2}INDEX: %s, KEY: %s (%s) VALUE: %s (%s)\n",  $index, $key, $mapArr[$key][0]["name"] , $line[$index], $mapArr[$key][0]["data"][$line[$index]]);
						$row["p"] = $mapArr[$key][0]["name"];
						$row["v"] = $mapArr[$key][0]["data"][$line[$index]];

						if($row["v"] == "True"){
							//printf("FIND ME, DAMNIT.  HERE IS ROW: \n");
							$row["v"] = $mapArr[$key][0]["name"];
							$row["p"] = "INTEREST";
							//var_dump($row);
						}
					

					}


					// now we have the property / value, we need to add the email hash
					$row['email'] = $currentEmail;

					// add all three to the output
					$outArr[] = $row;

					
				} else {
					// there is no data at this cell, in this line
				}


			}

			$index++;

		}

		
	}

	printf("{END} Count is: %s \n", $count);
	return $outArr;
}


function writeOutData($dataArray, $fileOutPath) {
	printf("writeOutData: ALIVE!\n");

	$headers = array("email_hash", "property", "value");

	$out_handle = fopen($fileOutPath, "w");

	// write line 1 :)
	fputcsv($out_handle, $headers);	

	$numLines = count($dataArray);

	foreach ($dataArray as $recordNum => $row) {

		//printf("[writeOutData] - writing line %s of %s - PROP: [%s] \t VAL:[%s]\n", $recordNum, $numLines, $row["p"], $row["v"]);

		$tmpRow = array($row["email"],$row["p"],$row["v"]);
		fputcsv($out_handle, $tmpRow);	

		//var_dump($tmpRow);

	}


	fclose($out_handle);

}

#bootstrap
main();

?>