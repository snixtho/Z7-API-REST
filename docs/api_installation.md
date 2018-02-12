# Installation Procedure
To install the api on a server, all you need to do is create the appropriate databases and then edit the settings files for the core and modules. The API script will automatically install itself on the first request if all databases and settings are set up correctly. Note that some modules follow with the API by default, an example would be the authentication module.

1. Copy all the files of the API to the folder where you wish to host it.

2. Install the modules you wish to use.

3. Follow the instruction for the core setup and each module you installed.

## Core Setup
1. Create a database for the core.

2. Open `settings.php` and edit the values to match your created database and preferences. Follow the comments in the file to understand what each setting does.

## Authentication Module Setup
1. Create a database for the auth module.

2. Open `auth_settings.php` and edit the values to match your created database and preferences.