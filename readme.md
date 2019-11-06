This is a simple project that aims to be a service that syncs the Github comments to the Trac comments after a Trac ticket is tied to a pull request. It works by being the webhook address for all GitHub's events and by posting to Trac via its XML-RPC api.

There are two kinds of GitHub comments:

1. The pull request comment
2. The code review comment made of:

- Many inline code review comments
- One final code review comment

To do:

- [x] Add new pull request comments
- [ ] Edit pull request comments
- [x] Add new code review comments
- [x] Add code review comments
- [ ] Respect the order of GH posting
