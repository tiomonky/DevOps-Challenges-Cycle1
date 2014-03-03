
##
## Challenge 5: Write a script that creates a Cloud Database.
##  If a CDB already exists with that name, suggest a new name like "name1" and give the user 
##  the choice to proceed or exit. The script should also create X number of Databases and X
##  number of users in the new Cloud Database Instance. The script must return the Cloud DB URL.
##  Choose your language and SDK!
##

import sys
import time
import pyrax

# Helper funcitons
##################
def list_database_instances(cdb):
   print ''
   print 'Available Instances'
   print '-------------------'
   for inst in cdb.list():
      print inst.name + ' (' + inst.status + ')'
   print ''

def list_instance_flavors(cdb):
   print ''
   print 'Available Instance Flavors'
   print '----------------------------'
   for flavor in cdb.list_flavors():
      print "%d) %s (%s)" % (flavor.id, flavor.name, flavor.ram)
   print ''
   return len(cdb.list_flavors())

def is_instance_name_valid(cdb, name):
   is_valid = True
   for inst in cdb.list():
      if inst.name == name:
         is_valid = False
   return is_valid

def is_instance_flavor_valid(cdb, flavor_id):
   is_valid = False
   for flavor in cdb.list_flavors():
      if int(flavor.id) == int(flavor_id):
         is_valid = True
   return is_valid

###############
# Main function
###############
def main():
   # Setting up the identity type
   pyrax.set_setting("identity_type", "rackspace")
   # Using credentials file
   pyrax.set_credential_file("../.rackspace_cloud_credentials")
   # Get the Cloud Database service
   cdb = pyrax.cloud_databases
   # List available instance flavors
   flavor_len = list_instance_flavors(cdb)
   while True: 
      instance_flavor = raw_input('Please choose a flavor (ex. 1): ')
      is_flavor_valid = is_instance_flavor_valid(cdb, instance_flavor)
      if not is_flavor_valid:
         print 'Mmmm.. i don\'t think that flavor is in the list. Please choose another one.'
      else:
         break

   list_database_instances(cdb)
   
   # Test if instance name is valid
   while True:
      # Prompt for instance name
      instance_name = raw_input('Please provide an instance name: ')
      # Validate name
      if is_instance_name_valid(cdb, instance_name):
         break
      else:
         print 'Wait, that name already exists. You may choose something like: ' + instance_name + '1'
         try_again = raw_input('Do you want to continue? [y|n] ')

         if try_again == 'n':
            sys.exit()

   # Create x number of databases and users
   num_of_dbs = raw_input('How many instance databases would you like to create? ')

   # The actual work
   try:
      # Add to the output buffer
      sys.stdout.write('Creating instance: ' + instance_name)
      # Flush the buffer
      sys.stdout.flush()
      # Create the instance
      new_instance = cdb.create(instance_name, flavor=int(instance_flavor))
      # This is to create some sort of progress bar. 
      while True:
         new_instance = cdb.get(new_instance.id)
         if new_instance.status == 'ACTIVE':
            break
         time.sleep(2)
         sys.stdout.write('.')
         sys.stdout.flush()
      print 'done.'
      # Create databases and users
      for x in xrange(1, int(num_of_dbs)):
         db_name = 'db_name' + str(x)
         print 'Creating database: ' + db_name
         new_instance.create_database('db_name' + str(x))

         db_user = 'db_user' + str(x)
         print 'Creating user: ' + db_user
         user = new_instance.create_user(name=db_user, password='top_passwd', database_names=db_name)
   except:
      print 'Error: Something bad happened', sys.exec_info()[0]
      raise

   # Output instance information
   if new_instance:
      print ''
      print 'Database Info'
      print '-------------'
      print 'Name: ' + new_instance.name
      print 'URL:  ' + new_instance.links[0]['href']
 
# Main execution
if __name__ == '__main__':
   main()

