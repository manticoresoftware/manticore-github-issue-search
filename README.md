# Manticore-GitHub-Issue-Search

Blogpost about this project - https://manticoresearch.com/blog/manticoresearch-github-issue-search-demo/

## Just run the demo locally

To run the project on your local machine, you need to have Docker installed with the compose plugin. Follow these commands to start:

```bash
git clone https://github.com/manticoresoftware/manticore-github-issue-search.git
cd manticore-github-issue-search/docker
cp .env.example .env
```

Specify your [github token](https://github.com/settings/tokens) ("Generate your token" -> "classic" -> specify name -> no checkboxes -> "Generate token") in `GITHUB_TOKENS=""` in the `.env` file.

```
docker-compose up
```

After completing these steps, the project should be accessible at [http://localhost/](http://localhost/).

The default port for the server is 80, so if you need to change it, update the `nginx` section in `app/config/app.ini.tpl`.

Remember to set up the necessary variables in the `.env` file before starting. This is where you can add your GITHUB tokens.

## Preparing for Deployment

If you aim to use this project beyond a Manticore Search demo, such as an alternative to GitHub's issue search, there's a method for deploying it on a remote server. First, install [yoda](https://github.com/Muvon/yoda) on your machine and familiarize yourself with its documentation.

#### Setting Up Your GitHub Token

You will need a GitHub token to utilize the GitHub API. Ensure that your token is set in your environment on the user account you're using to deploy.

You should add the `GITHUB_TOKENS` to the remote server's environment. Usually, this can be done by adding it to the `~/.bashrc` file like so:

```bash
export GITHUB_TOKENS=...
```
Remember, if you haven't added any tokens, you're limited to 60 requests per hour. That's really not much for indexing an average repository.

#### Deployment

For deployment, tweak the `docker/Envfile` with your server details and make sure you have passwordless authentication set up with your SSH key. Then, simply run:

```bash
yoda deploy --env=production
```

#### Setting Up a New Server in 5 Steps

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
