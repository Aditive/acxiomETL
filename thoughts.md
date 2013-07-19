thoughts.md

so what i need is a function that'll take in all the headers

the output array is a reduced set of headers
	where, if a headder matches a pattern, it is lumped into a category

	if not, it's just a headding!



so the output will be a list of headers and categories

if it's a category, we need to know the 'length' of possible values in a row
	that is, the "aduleAge" category can have 7 different values... so once we encounter then beginning of the AdultAge category
		we know that we need to read the next 6 (not including the one we're currently on) cells to the right looking for a NON DEFAULT VALUE
			if we find a non default value, then we know that the cell (say 5) where the NON DEFAULT value occurs is the 'value' for our category.


	adultAge	=> array(
								"18_24" => "18-24",	//cell 0
								"25_34" => "25-34",	//cell 1
								"35_44" => "35-44",	//cell 2
								"45_54" => "35-44",	//cell 3 
								"55_64" => "55-64",	//cell 4 
								"65_74" => "65-74",	//cell 5 <= THIS IS WHERE WE FOUND NON DEFAULT VALUE, so the valye for adultAge cat. is "56-74" 
								"75+" 	=> "57+",	//cell 6
								)