<?php

/*
By: Juan Hernandez

Challenge 3: Write a script that prints a list of all of the DNS domains on an account. Let the user select a domain from the list and add an "A" record to that domain by entering an IP Address TTL, and requested "A" record text. This must be done in PHP with php-opencloud.
*/

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

// List available DNS domains
//
function printDNSDomains($dns) {
   // Get the domain list
   $domains = $dns->domainList();
   if ( count($domains ) > 0 ) {
      print "\n";
      print "Domain List\n";
      print "---------------------\n";
      // Iterate through the collection
      while ( $domain = $domains->next() ) {
         print "$domain->name\n";
      }
      print "\n";
   }
}

// Get the user specified domain
// Input: $dns - the environment
//        $domain - user specified domain
// return: domain
function getDomain($dns, $specified_domain) {
   // Take care of case sensitivity
   $specified_domain = strtolower($specified_domain);
   // Get domain list
   $domains = $dns->domainList();
   // First check that there are domains 
   if ( count($domains) > 0 ) {
      // Loop through the list
      while ( $domain = $domains->next() ) {
         if ( strpos( strtolower($domain->name), $specified_domain ) !== false ) {
            $specified_domain = $domain;
            break;
         }
      }
      return $specified_domain;
   }
}

// Print domain record list
// Input: domain - the domain you want to print
//
function printDNSRecords($domain) {
   // Get record list
   $records = $domain->recordList();
   // Check that there are domains to display.
   if ( count($records) > 0 ) {
      print "\n";
      // print header
      print "$domain->name's records\n";
      print "------------------------------------------\n";
      // Get record list
      $records = $domain->recordList();
      // Loop through the records and display them "nicely"
      while ( $record = $records->next() ) {
         echo sprintf("%-7s%-40s%-7s%-35s\n",$record->ttl, $record->name, $record->type, $record->data);
      }
      print "\n";
   }
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

// Initialize the DNS service
$dns = $client->dnsService('cloudDNS');
// List available DNS domains
printDNSDomains($dns);
// Prompt for the server flavor
$specified_domain = readline("Which domain would you like to manage? (ex: test.com) ");
$domain = getDomain($dns, $specified_domain);
// Test that the domain exists
if ( !(is_object($domain) ) ) {
   print "Error - The domain provided does not exist.\n";
   exit;
}
// Print available records for the specified domain
printDNSRecords($domain);

do {
   // Prompt for record information
   $record_type = readline("Please provide the type of record you want to add (ex: A|MX|CNAME): ");
   $record_name = readline("Please provide the name of the domain or subdomain you want to add (ex: test.mydomain.com): ");
   $record_ip   = readline("Please provide the IP or domain/subdomain associated with the record: ");
   $record_ttl  = readline("Please provide the TTL for the record: ");
   // Define the record to create
   $record = $domain->record(array(
      'ttl'  => $record_ttl,
      'name' => $record_name,
      'data' => $record_ip,
      'type' => $record_type
   ));
   try {
      // Create the record
      $record->Create();
   } catch (\Guzzle\Http\Exception\BadResponseException $e) {
      // No! Something failed. Let's find out:
      $responseBody = (string) $e->getResponse()->getBody();
      $statusCode   = $e->getResponse()->getStatusCode();
      $headers      = $e->getResponse()->getHeaderLines();

      echo sprintf('Status: %s\nBody: %s\nHeaders: %s', $statusCode, $responseBody, implode(', ', $headers));
   }
   // Sleep for 2 seconds to wait for the record to update
   sleep(2);
   // Print domain's records
   printDNSRecords($domain);
   // Are we creating more records?
   $keep_updating = readline("Do you want to add another record? [y|n] ");
}while( $keep_updating == 'y' );

?>
