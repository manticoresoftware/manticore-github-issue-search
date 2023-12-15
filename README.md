# Manticore-GitHub-Issue-Search

## Getting Started

First, install [yoda](https://github.com/Muvon/yoda) on your computer and familiarize yourself with the documentation.

## Setting Up Your GitHub Token

To access the GitHub API, you'll need to use your GitHub token. Make sure your token is set in your environment under the user account you're deploying with.

Typically, you should add the export commands to your `~/.bashrc` file like this:

```
export GITHUB_TOKEN_0=...
export GITHUB_TOKEN_1=...
...
```

On your personal machine, you may only need `_0`. However, if you're using multiple workers, refer to `docker/Envfile` for production settings—we use three tokens for three workers in our showcase.

To obtain an API token, visit GitHub and create one.

## Running It Locally

To run the project locally, execute the following command in the project's directory:
```bash
yoda start
```

Then, add the local domain to your `/etc/hosts` file:

```
127.0.0.1 manticore-github-issue-search.zz
```

Now, you can open the following URL in your web browser: http://manticore-github-issue-search.zz

## Deployment

To deploy, adjust the `docker/Envfile` for your server settings and ensure passwordless authentication with your SSH key. Simply run:

```bash
yoda deploy --env=production
```

## Preparing a New Server in 5 Steps

1. Set up a new server with Rocky Linux 9 as the base OS.
2. Include your server's IP in the `Envfile`.
3. To initialize the server, execute:

    ```bash
    yoda setup --host=server-ip
    ```

4. Start the deployment process with:

    ```bash
    yoda deploy --host=server-ip
    ```

5. That's it!
