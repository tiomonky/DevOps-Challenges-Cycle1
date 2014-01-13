<?php

require '../../../vendor/autoload.php';

use OpenCloud\Rackspace;
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

// Find the flavor that matches the specified server memory size
// Input: $compute     - the compute environment
//        $server_size - user specified server size in memory
// return: flavor id
function getServerFlavor($compute, $server_size) {
   // Initialize variable
   $specified_flavor = -1;
   // Take care of case sensitivity
   $server_size = strtoupper($server_size);
   // Get all flavors in the specified data center
   $flavors = $compute->flavorList();
   // Loop through all flavors in the data center
   while ( $flavor = $flavors->next() ) {  
      // Get flavor name
      $flavor_name = strtoupper($flavor->name);
      // Compare with specified size
      if ( strpos( $flavor_name, $server_size ) !== false ) {
         $specified_flavor = $flavor;
         break;
      }
   }

   return $specified_flavor;
}

// List all available flavors
// 
function printServerFlavors($compute) {

   print "-----------------\n";
   print "Available Flavors\n";
   print "-----------------\n";

   // Get all flavors in the specified data center
   $flavors = $compute->flavorList();
   // Loop through all flavors in the data center
   while ( $flavor = $flavors->next() ) {
      print "$flavor->name\n";
   }
}

// Get the server image that matches the specified OS
// Input: $compute - the data center environment
//        $server_image - the user specified OS to look for
// return: image id
function getServerImage($compute, $server_image) {
   // Initialize variable
   $specified_image = -1;
   // Take care of case sensitivity
   $server_image = strtolower($server_image);
   // Get specified image
   $images = $compute->imageList();
   while ( $image = $images->next() ) {
      if ( strpos( strtolower($image->name), $server_image ) !== false ) {
         $specified_image = $image;
         break;
      }
   }
   return $specified_image;
}

// List all available images
// 
function printServerImages($compute) {

   print "----------------\n";
   print "Available Images\n";
   print "----------------\n";

   // Get all flavors in the specified data center
   $images = $compute->imageList();
   // Loop through all flavors in the data center
   while ( $image = $images->next() ) {
      print "$image->name\n";
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

// Welcome message
print("***************************************************************\n");
print("                        Challenge 1\n");
print("***************************************************************\n");

do {
   $valid_dc = false;
   // Prompt for data center
   $data_center = strtoupper(readline("Which data center do you your server created? [DFW|IAD|ORD|SYD|HKG] "));
   // Check if the DC is valid
   if ( isDCValid( $client, $data_center ) ) {
      $valid_dc = true;
   }else { print "\nSorry, you have provided an invalid DC. Please try again\n\n";}

}while ( !$valid_dc );

// Initialize with the data center
$compute = $client->computeService('cloudServersOpenStack', $data_center);

// Prompt for the server flavor
printServerFlavors($compute);
$server_flavor = readline("How much memory do you need on your server? (ex: 512MB|4gb) ");
$server_flavor = getServerFlavor($compute, $server_flavor);

// Prompt for server image
printServerImages($compute);
$server_image = readline("What OS would you like? (ex: ubuntu|windows|centos) ");
$server_image = getServerImage($compute, $server_image);

// Let's create the server
print "\nCreating Cloud Server...";
// Create server object
$server = $compute->server();
// Create server
try {
    $response = $server->create(array(
        'name'     => 'challenge1_server',
        'image'    => $server_image,
        'flavor'   => $server_flavor,
        'networks' => array(
            $compute->network(Network::RAX_PUBLIC),
            $compute->network(Network::RAX_PRIVATE)
        )
    ));
} catch (\Guzzle\Http\Exception\BadResponseException $e) {

    // No! Something failed. Let's find out:

    $responseBody = (string) $e->getResponse()->getBody();
    $statusCode   = $e->getResponse()->getStatusCode();
    $headers      = $e->getResponse()->getHeaderLines();

    echo sprintf('Status: %s\nBody: %s\nHeaders: %s', $statusCode, $responseBody, implode(', ', $headers));
}

// Function that checks the progress of the build
$callback = function($server) {
    if (!empty($server->error)) {
        var_dump($server->error);
        exit;
    } else { print "."; }
};

// Call the function every 600 seconds until the server is in an ACTIVE state
$server->waitFor(ServerState::ACTIVE, 600, $callback);

print "Done.\n";
print "\n\nNew Server Info\n";
print "-------------------------\n";
print "Name: $server->name\n";
print "IP:   $server->accessIPv4\n";
print "Pass: $server->adminPass\n";

?>
