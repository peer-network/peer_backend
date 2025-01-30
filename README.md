# Peerapipg

## Version 1.0.0

### Peer GraphQL PHP

---

### Installation

1. **Install Composer**:
   Follow the instructions at [Composer](https://github.com/composer/composer) to install Composer.

2. **Install Dependencies**:
   ```sh
   composer install
   ```

3. **Database Setup**:
   - Ensure that PostgreSQL Database Server is installed and configured. You can download it from [postgresql.org](https://www.postgresql.org/download/).
   - Determine the PostgreSQL version required for compatibility (16.3).
   - Execute the SQL files located in the `/sql/` directory to set up the database schema and any necessary seed data. Use the `psql` command or a database management tool like pgAdmin.

4. **Create Required Folders**:
   Create the following folders in the root directory with write permissions:
   ```sh
   mkdir -p runtime-data/logs || runtime-data/cache || runtime-data/ratelimiter || runtime-data/media/audio || runtime-data/media/image || runtime-data/media/text || runtime-data/media/video || runtime-data/media/other || runtime-data/media/userData || runtime-data/media/profile
   chmod -R 775 runtime-data/
   ```
   These folders are required for logging and caching purposes:
   - ` runtime-data/logs/`: Used by the `LoggerInterface` to store logs.
   - ` runtime-data/cache/`: Used for Dependency Injection and caching.
   - ` runtime-data/ratelimiter/`: Used by the `ratelimiter` to rate.
   - ` runtime-data/media/video/, runtime-data/media/audio/, runtime-data/media/text/, runtime-data/media/image/, runtime-data/media/other/, runtime-data/media/userData, runtime-data/media/profile`: Used for Uploaded Media Data.

5. **Environment Configuration**:
   - Rename `.env.schema` to `.env`.
   - Populate the `.env` file with the necessary variables. Here are some common variables:
     ```plaintext
     DB_HOST=localhost
     DB_PORT=5432
     DB_USER=your_username
     DB_PASSWORD=your_password
     DB_NAME=your_database_name
     ```
   - Ensure the environment variables are correctly configured by running `printenv` on Unix-based systems.

6. **Start the Server**:
   Start the PHP built-in server for development purposes:
   ```sh
   php -S localhost:8888 -t public/
   ```
   Ensure the command is executed from the project root directory. Note that this server is suitable only for development, not production.

7. **Test with Postman**:
   - Set the GraphQL endpoint in Postman to:
     ```
     http://localhost:8888/graphql
     ```
   - Configure any required headers, such as `Content-Type: application/json`.
   - Use the following example query to test the endpoint:
     ```graphql
     {
       hello {
         root
         args
		 context
		 currentuserid
       }
     }
     ```
   - You can change the port as needed.

### Project Structure that is compatible with automation (optional_data.sql is optional).
Note:    We plan to delete the keys-directory and runtime-data-directory once they are both configurable by .env.
Note2:   One .env line per subdirectory of runtime-data (cache, logs, media) and one for the keys directory (already exists).

```plaintext
project_root/
├── keys/
│   └── placeholder.txt          //this file needs to exist. Empty Folders can't be commited via Git. (mandatory)
├── runtime-data/
│   └── cache/
         └── placeholder.txt     //this file needs to exist. Empty Folders can't be commited via Git. (mandatory)
│   └── logs/
         └── placeholder.txt     //this file needs to exist. Empty Folders can't be commited via Git. (mandatory)
│   └── ratelimiter/
         └── placeholder.txt     //this file needs to exist. Empty Folders can't be commited via Git. (mandatory)
│   └── media/
│       ├── audio/
            └── placeholder.txt  //this file needs to exist. Empty Folders can't be commited via Git. (mandatory)
│       ├── image/
            └── placeholder.txt  //this file needs to exist. Empty Folders can't be commited via Git. (mandatory)
│       ├── other/
            └── placeholder.txt  //this file needs to exist. Empty Folders can't be commited via Git. (mandatory)
│       ├── profile/
            └── placeholder.txt  //this file needs to exist. Empty Folders can't be commited via Git. (mandatory)
│       ├── text/
            └── placeholder.txt  //this file needs to exist. Empty Folders can't be commited via Git. (mandatory)
│       ├── userData/
            └── placeholder.txt  //this file needs to exist. Empty Folders can't be commited via Git. (mandatory)
│       └── video/
            └── placeholder.txt  //this file needs to exist. Empty Folders can't be commited via Git. (mandatory)
├── sql_files_for_import/
    ├──.env.schema               //contains some needed informations for the deployment of the database. (mandatory)
│   ├──optional_data.psql        //contains additional data for Postgres (entries into the tables) (optional)
│   └── structure.psql           //contains the mandatory data for Postgres (e.g. Tables) (mandatory)
├── .env.schema                  //contains needed informations for the deployment of the Backend. (mandatory)
├── composer.json                //this file describes the dependencies of the project and may contain additional metadata.   (mandatory)
├── composer.lock                //contains the exact versions of the packages . (mandatory)  
```

### Additional Steps

- **Folder Permissions**:
  - Ensure `runtime-data/logs/`, `runtime-data/cache/`, and `runtime-data/media/` directories are writable. This can be achieved by setting the appropriate permissions:
    ```sh
    chmod -R 775 runtime-data/
    ```

- **Environment Variables**:
  - Verify that all required environment variables are defined in the `.env` file, and adjust according to your setup.

With these detailed instructions, you should be able to set up and run the Peerapipg application efficiently. If you encounter any issues, please consult the documentation or seek assistance from the Peer community.