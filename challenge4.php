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
$service = $client->objectStoreService('cloudFiles');
// List available containers
printContainers($service, 30);

do {
   // Prompt for container to create
   $container_name     = readline("Please provide a name for your new container: ");
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
   
   try {
      // Create the container
      $success = $service->createContainer($container_name);
      // Test for successful creation
      if ( !$success ) { print "Error - Container creation failed. More than likely the container already exists.\n"; exit; }
      // Now grab the new container
      $container = $service->getContainer($container_name);
      // Test that we got a container back
      if ( !( is_object($container) ) ) {
         print "Error - There was an error getting the new container\n";
         exit;
      }
      // Synchronize the specified directory to the container
      //$container->uploadDirectory($directory_to_upload);
      // CDN enable the container   
      $container->enableCdn();
      // Get the CDN object to access its CDN functionality
      $cdn = $container->getCdn();
      // Get the CDN access URLs
      $url_http  = $cdn->getCdnUri();
      $url_https = $cdn->getCdnSslUri(); 
      // Print container info
      print "Container information\n";
      print "---------------------\n";
      print "Container name:  $container->name\n";
      print "CDN URL (HTTP):  $url_http\n";
      print "CDN URL (HTTPS): $url_https\n\n";
      
   } catch (\Guzzle\Http\Exception\BadResponseException $e) {
      // No! Something failed. Let's find out:
      $responseBody = (string) $e->getResponse()->getBody();
      $statusCode   = $e->getResponse()->getStatusCode();
      $headers      = $e->getResponse()->getHeaderLines();

      echo sprintf('Status: %s\nBody: %s\nHeaders: %s', $statusCode, $responseBody, implode(', ', $headers));
   }

} while ( true );

?>
