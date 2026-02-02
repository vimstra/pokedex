1. install Docker on your computer
2. if there's 'data' directory in the project folder - delete it
3. type "docker compose up --build -d"" in the commandn line and hit enter
4. go to: http://localhost:8080/index.php
5. after closing type "docker compose down -v" in the command line to stop the app

authentication:

admin:

- username: admin
- password: admin

trainer:

- username: common
- password: common
