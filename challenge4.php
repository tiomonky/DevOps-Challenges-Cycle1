<?php

/*
By: Juan Hernandez

Challenge 4: Write a script that creates a Cloud Files Container. If the container already exists, exit and let the user know. The script should also upload a directory from the local filesystem to the new container, and enable CDN for the new container. The script must return the CDN URL. This must be done in PHP with php-opencloud.
*/

require '../../../vendor/autoload.php';

use OpenCloud\Rackspace;

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

// Print container list
// Input: store - the object store service
//        limit - how many do you want to return
//
function printContainers($store, $limit) {
   // Get container list
   $containers = $store->listContainers(array('limit' => $limit));
   // Check that there are containers to display.
   if ( count($containers) > 0 ) {
      print "\n";
      // print header
      print "Available Containers\n";
      print "------------------------\n";
      // Loop through the containers and display them "nicely"
      while ( $container = $containers->next() ) {
        print "$container->name\n"; 
      }
      print "\n";
   }
}

// Test if a container already exists
// Input: store - the object store service
//        container_name - Name of the container to look for
//
function doesContainerExist($store, $container_name) {
   $found = false;
   // Get container list
   $containers = $store->listContainers();
   // Check that there are containers to display.
   if ( count($containers) > 0 ) {
      // Loop through the containers and display them "nicely"
      while ( $container = $containers->next() ) {
        if ( $container->name == $container_name) {
           $found = true;
           break;
        }
      }
   }
   return $found;
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
print("                        Challenge 4\n");
print("***************************************************************\n");

// Initialize the object store service
$service = $client->objectStoreService('cloudFiles', 'DFW');
// List available containers
printContainers($service, 30);

//do {
   // Prompt for container to create
   $container_name = readline("Please provide a name for your new container: ");
   // Validate container name
   $service->checkContainerName($container_name);
   
   try {
      // Now grab the new container
      $found = doesContainerExist($service, $container_name);
      // Test if it already exists
      if ( !$found ) {
         // Create the container
         print "Creating container $container_name...";
         $success = $service->createContainer($container_name);
         // Test for successful creation
         if ( !$success ) { print "Error - Container creation failed. More than likely the container already exists.\n"; exit; }
         else { print "done.\n"; }
         // Now grab the new container
         $container = $service->getContainer($container_name);
      } else {
         print "INFO - Container $container_name already exists.\n";
      }
      // CDN enable the container   
      print "Enabling CDN features on the container...";
      $container->enableCdn();
      print "done.\n";
      // Get the CDN object to access its CDN functionality
      $cdn = $container->getCdn();
      // Get the CDN access URLs
      $url_http  = $cdn->getCdnUri();
      $url_https = $cdn->getCdnSslUri(); 
      // Print container info
      print "\nContainer information\n";
      print "---------------------\n";
      print "Container name:  $container->name\n";
      print "CDN URL (HTTP):  $url_http\n";
      print "CDN URL (HTTPS): $url_https\n\n";

      // Display how the sync works
      print "\n";
      print "= Information about synchronizing directories =\n";
      print "\n";
      print "Local                Remote               Comparison          Action\n";
      print "-------------------  -------------------  ------------------  ----------------------------\n";
      print "File exists          File does not exist  Identical checksum  No action\n";
      print "File exists          File does not exist  Different checksum  Local file overwrites remote\n";
      print "File exists          File does not exist  -                   Local file created in Swift\n";
      print "File does not exist  File exists          -                   Remote file deleted\n";
      print "\n";
      $directory_to_upload = readline("Please provide a directory to synchronize with your new container: ");   
      // TO_DO - getting errors when calling this function.
      // Synchronize the specified directory to the container
      //print "Syncing local directory $directory_to_upload with container $container_name...";
      //$container->uploadDirectory($directory_to_upload);
      //print "done.\n";
 
   } catch (\Guzzle\Http\Exception\BadResponseException $e) {
      // No! Something failed. Let's find out:
      $responseBody = (string) $e->getResponse()->getBody();
      $statusCode   = $e->getResponse()->getStatusCode();
      $headers      = $e->getResponse()->getHeaderLines();

      echo sprintf('Status: %s\nBody: %s\nHeaders: %s', $statusCode, $responseBody, implode(', ', $headers));
   }

//} while ( true );

?>
