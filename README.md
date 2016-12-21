Packages
========

> Source code repository management made simple.

Just a copy of https://github.com/terramar-labs/packages With following changes:

1. Delete a package from edit package page.
2. For those how cannot work with SSH URLs for git clone, I am setting WEB URL as SSH URL.


[![Build Status](https://img.shields.io/travis/terramar-labs/packages/master.svg?style=flat-square)](https://travis-ci.org/terramar-labs/packages)

Packages extends [Satis](https://github.com/composer/satis), adding useful management functionality like GitHub
and GitLab integration.

Packages automatically registers GitLab and GitHub project web hooks to keep Satis up to date every time you
push code. Packages also features a web management interface that allows for easy management of exposed
packages and configured source control repositories.

Packages version 3 works on a plugin based system based around source code repositories. Packages
can trigger, with each code push, many automated tasks like documentation generation or code 
analysis. The simple event-based architecture allows easy creation of new automation tasks.

[View the docs online](http://docs.terramarlabs.com/packages/3.1)
