# Active User Directory Checker

checked vault for users with qualified job code and will send a email to create a ticket if a qualified job code is tied to a user without a device.

build container with ```docker build -t teach-check . ``` inside the directory

run with ```docker run --rm --name teach-check teach-check ```
## Run from crontab
To run this script from a crontab, you can add the following line to your crontab file:
```bash
0 * * * * /usr/bin/docker run --rm   --log-driver=syslog --log-opt syslog-address=udp://localhost:514   --log-opt tag=teach-check   --name teach-check teach-check >> /home/webadmin/teacher-no-device.log 2>&1 &&   date > /home/webadmin/teacher-no-device-lastrun.txt
```


you'll need to provide a .env file with the following variables
```
VUSER=
VPASS=
VLOC=
VDATA=
RECIPIENTEMAIL=
SENDEREMAIL=
MAILHOST=
```