## Purpose
fixit.php was created to move WordPress between domains or directories

## Usage
Place the fixit.php file in the root directory of any WordPress installation. Move the WordPress installation directory to a new URL, this can be either a new domain or just another folder. Access fixit.php at the new location. Fixit will access your db-settings and suggest what URL to move from and to, all you need to do is execute and the WordPress database will be updated with the new location.

## What it does
Fixit will search every `TABLE` of your database, search for the `search string` in each `COLUMN`.

**For each matched cell it will:**
*If the cell contained a serialized dataset fixit will deserialize it and check the data in the resulting array.*
Replace all instances of the `search string`, and nothing else, with the `replacement string`.
*If the cell contained a serialized dataset fixit will reserialize.*
Then the new data is inserted in the cell.

## History

 *  Author: Tamm Sjödin
 *  Created: February 2011
 *  Latest update: December 1st 2011
 *  Version: 1.0
