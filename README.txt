# CF Tag Browser Plugin

This plugin adds the ability to browse all posts that have the tag you select in the tag browser window. You can limit the tags that appear in the tag browser window by category via the category dropdown at the top of the tag browser.

## Basic Usage

The tag browser can be accessed on its own page via a defined URL slug, added to a template via function call, or added to posts and pages via shortcode.

To access the tag browser on its own page via URL slug:
- Visit the tag browser settings page in wp-admin by clicking Settings->CF Tag Browser.
- Set Auto-create Tag Browser Page to yes.
- Define the slug and page title you'd prefer
- Visit http://yourdomain/your-chosen-slug

To add the Tag Browser to a template via function call, add the following code to your template file:
<?php if (function_exists("cftb_browser")) { cftb_browser(); } ?>

To add the Tag Browser to a post or page, insert the following shortcode to the post or page:
[tag-browser]
