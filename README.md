# Active User Directory Checker

checked vault for users with qualified job code and will send a email to create a ticket if a qualified job code is tied to a user without a device.

build container with ```docker build -t teach-check . ``` inside the directory

run with ```docker run --rm --name teach-check teach-check ```



you'll need to provide a .env file with the following variables
```
VUSER=
VPASS=
VLOC=
VDATA=
```