# Manticore-GitHub-Issue-Search

## Getting Started

To run the project on your local machine, you need to have Docker installed with the compose plugin. Follow these commands to start:

```bash
cd docker
cp .env.example .env
docker-compose up
# Create required structure in Manticore
docker exec -i manticore-github-issue-search.manticore mysql < dump.sql
```

After completing these steps, the project should be accessible on your localhost.

The default port for the server is 80, so if you need to change it, update the `nginx` section in `app/config/app.ini.tpl`.

Remember to set up the necessary variables in the .env file before starting. This is where you can add your GITHUB tokens.

## Preparing for Deployment

First, install [yoda](https://github.com/Muvon/yoda) on your machine and get familiar with its documentation.

## Setting Up Your GitHub Token

You will need a GitHub token to utilize the GitHub API. Ensure that your token is set in your environment on the user account you're using to deploy.

You should add the `GITHUB_TOKENS` to the remote server's environment. Usually, this can be done by adding it to the `~/.bashrc` file like so:

```bash
export GITHUB_TOKENS=...
```

## Deployment

For deployment, tweak the `docker/Envfile` with your server details and make sure you have passwordless authentication set up with your SSH key. Then, simply run:

```bash
yoda deploy --env=production
```

## Setting Up a New Server in 5 Steps

1. Initialize a new server with Rocky Linux 9 as the base OS.
2. Add your server's IP address to the `Envfile`.
3. Run the following command to set up the server:

    ```bash
    yoda setup --host=server-ip
    ```

4. Begin the deployment process with:

    ```bash
    yoda deploy --host=server-ip
    ```

5. You're all set!
