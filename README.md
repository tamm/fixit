## Purpose
fixit.php was created to move WordPress between domains or directories.
It also works to make general search and replace in a WordPress installation.

fixit.php will access your WordPress database settings and find what URL your WordPress has been installed on and use this as the search term, it will use the current domain and directory as the replace, all you need to do is execute and the WordPress database will be updated with the new location of your WordPress.


## Usage
Place the fixit.php file in the root directory of any WordPress installation.

Move the WordPress installation directory to a new URL, this can be either a new domain or just another folder.

Access fixit.php at the new location.

Click the replace button.

If you want to move the WordPress AFTER you execute this script, simply edit the replace field before running.

If you want to search and replace something completely different, like the word "fixit" to "fixit.php", just put this in the search and replace parameters and run it.

## What it does
Fixit will find every `TABLE`, and `COLUMN` of your database, it will then `UPDATE` any rows containing the `search string`.

**For each matched cell it will:**
*If the cell contained a serialized dataset fixit will deserialize it and check the data in the resulting array.*
Replace all instances of the `search string`, and nothing else, with the `replacement string`.
*If the cell contained a serialized dataset fixit will reserialize.*
Then the new data is inserted in the cell.

## History

 *  Author: Tamm Sjödin
 *  Created: February 2011
 *  Latest update: October 2017
 *  Version: 1.1


 *  Author: Tamm Sjödin
 *  Created: February 2011
 *  Latest update: December 1st 2011
 *  Version: 1.0
