# Overview #

This WordPress plugin allows clients to hook up an instagram account, the Instagram API will then be called every 15 minutes on a cron and posts will be saved to an insta-post post type. These can then be displayed however it makes sense.

# Setup #

The settings panel is found under Settings > Instagram. To connect an account log into the account at instagram.com in a seperate tab and then click connect. This will follow through the authorisation process and save a token. The 'Fetch Posts' button will initiate a manual fetch.

# Content #

Instagram data will be saved to custom fields. Images will also be downloaded and attached to the post as a featured image. If there are posts existing without featured images there is an action under settings to download and attach these.

# Extending #

The action 'fetch_insta_inserted_post' is called after each new post is inserted. This can be used to modify the post, for instance adding terms to it.
