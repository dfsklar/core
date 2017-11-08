#!/usr/bin/env bash

# ONLY FOR UPDATING FORUM JS!!

# does not update admin JS

# This script compiles Flarum's core assets so that they can be used in-browser.
# It should be run from the root directory of the core.

/bin/rm -f ../../assets/forum*.js

base=$PWD

cd "${base}/js/forum"
gulp

