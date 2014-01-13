<?php

require '../../../vendor/autoload.php';

use OpenCloud\Rackspace;
use OpenCloud\DNS;
use OpenCloud\DNS\Resource;
use OpenCloud\Compute\Constants\Network;
use OpenCloud\Compute\Constants\ServerState;

// Read credentials from a file
// input: file name containing credentials
// Returns: Array with 1) username 2) ApiKey
function ReadCredentials($input_file = ".rackspace_cloud_credentials") {
   $handle = fopen($input_file, "r");

   if ( $handle ) {
      while (( $line = fgets( $handle )) !== false ) {
        return explode(':', trim($line));
      }
   }
   else {
      // problems opening the file
     echo "Error - There was a problem reading credentials file";
     exit(0);
   }
}

// Check data centers with existing endpoints
// Input: $client - SDK client to the Rackspace API
//        $data_center - the specified data center
function isDCValid($client, $data_center) {
   // Initialize return value to false
   $is_valid = false;
   // Take care of capitalization
   $data_center = strtoupper($data_center);

var_dump($client->getCatalog());

   // Get all the items in the catalog
   $catalog = $client->getCatalog()->getItems();

   // Loop through all items in the catalog
   foreach ( $catalog as $catalog_item ) {
      // Get the Cloud Servers endpoints
      if ($catalog_item->getName() == "cloudServersOpenStack") {
         // Get endpoints
         $endpoints = $catalog_item->getEndpoints();
         // Loop through all available data centers
         foreach ( $endpoints as $e ) {
            // Look for it to see if it is valid 
            if ( $e->region == $data_center ) {
               $is_valid = true;
            }
         }
      }
   }
   return $is_valid;
}

// List available DNS domains
//
function printDNSDomains($dns) {
   // Get the domain list
   $domains = $dns->domainList();
   print "---------------------\n"; 
   print "Available DNS Domains\n";
   print "---------------------\n";
   // Iterate through the collection
   while ( $domain = $domains->next() ) {
      print "$domain->name\n";
   }
}

// Get the user specified domain
// Input: $dns - the environment
//        $domain - user specified domain
// return: domain
function getDomain($dns, $specified_domain) {
   // Take care of case sensitivity
   $specified_domain = strtolower($specified_domain);
   // Get specified domain
   $domains = $dns->domainList();
   while ( $domain = $domains->next() ) {
      if ( strpos( strtolower($domain->name), $specified_domain ) !== false ) {
         $specified_domain = $domain;
         break;
      }
   }
   return $specified_domain;
}

/////////////////////////////////////////////////////////////////////////////////

// Get Credentials from the file
$credentials = ReadCredentials();
// Authenticate the client
$client = new Rackspace(Rackspace::US_IDENTITY_ENDPOINT, array(
	'username' => $credentials[0],
	'apiKey'   => $credentials[1]
));
// Force authentication
$client->authenticate();

print("***************************************************************\n");
print("                        Challenge 3\n");
print("***************************************************************\n");

// Initialize with the DFW data center
$dns = $client->dnsService('cloudDNS');
// List available DNS domains
printDNSDomains($dns);


/*
Challenge 3: Write a script that prints a list of all of the DNS domains on an account. Let the user select a domain from the list and add an "A" record to that domain by entering an IP Address TTL, and requested "A" record text. This must be done in PHP with php-opencloud.
*/

// Prompt for the server flavor
$specified_domain = readline("Which domain would you like to manage? (ex: test.com) ");
$domain = getDomain($dns, $specified_domain);

// Prompt for record information
$record_type = readline("Please provide the type of record you want to add: ");
$record_name = readline("Please provide the name of the record you want to add: ");
$record_ip   = readline("Please provide the IP associated with the record: ");
$record_ttl  = readline("Please provide the TTL for the record: ");

$record = new OpenCloud\DNS\Resource\Record(array(
   'ttl'  => $record_ttl,
   'name' => $record_name,
   'data' => $record_ip,
   'type' => $record_type
));

$domain->addRecord($record);
$domain->Update();
$records = $domain->recordList();

while ( $record = $records->next() ) {
   print "$record->name\n";
}
?>
