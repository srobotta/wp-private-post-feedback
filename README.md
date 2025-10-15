# Wordpress Plugin Private Post Feedback

This plugin allows to send feedback to the author and admin of a post. The post type can
be selected where the feedback form should appear. Also, selected post types can be rated
by clicking a star rating value.

## Installation

1. Create a new diretory `private-post-feedback` in your Wordpress plugins directory.
1. Copy the contents of thius repository inside the new directory.
1. Login to your Wordpress admin panel and go to Plugins. The new plugin should be listed there.
1. Click on "Activate" to enable the plugin.
1. Go to the plugin settings and add the post types where you wish that the feedback form should appear and save the setting.

## Usage

When a post is viewed, near at the end a link "Leave feedback" appears. By clicking the link
the feedback form is shown. The visitor may enter a message and save/send the form.
The message is stored in Wordpress and the author of the post and the admin of the site receive
an email notification about a new feedback.

Next to the "Leave feedback" link a list of stars for a rating appear. By clicking one of
the stars, a rating of that post is submitted and collected in the post meta data.