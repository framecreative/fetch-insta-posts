# Overview #

This WordPress plugin allows clients to hook up an instagram account, the Instagram API will then be called every 15 minutes on a cron and posts will be saved to an insta-post post type. These can then be displayed however it makes sense.

# Setup #

The settings panel is found under Settings > Instagram. To connect an account log into the account at instagram.com in a seperate tab and then click connect. This will follow through the authorisation process and save a token. The 'Fetch Posts' button will initiate a manual fetch.

# Content #

Instagram data will be saved to custom fields. Images will also be downloaded and attached to the post as a featured image. If there are posts existing without featured images there is an action under settings to download and attach these.

# Extending #

The action 'fetch_insta_inserted_post' is called after each new post is inserted. This can be used to modify the post, for instance adding terms to it.


# Version 2 #

Account connection will need to be refreshed after install because new tokens are needed. Image width and height meta information won't be saved on posts, but can be accessed from the thumnail attachment.

## Version 2.1 ##

Add support for Carousel Images (loads first image only). Change to a 'create or update' strategy to update posts in the feed if data has changed (ie: image).

# A word on tokens #

Facebook / Instagram seem determined not to approve out application, so for this to work each client account must be added as a "tester" to the Frame Facebook Feed AP under the 'Instagram basic display' section.
