# drush-display-fields
Drush commands to alter Drupal 8 field display settings

The meat of this module is the ability to bulk update one, several, or all node bundles by setting one, several, or all fields to be displayed.

To use it:
1. Select one or more node bundles. For example, we'll be modifying `blog` and `page`.
1. Select the display you wish to modify. `default` is the default display, but for our example we'll be modifying `teaser`.
1. Select one or more fields you want to show in the display. For example, `field_thumbnail` and `field_summary`.
1. Choose other options. For example, we don't want labels to display on the `teaser` view.
1. Run the drush command with your options.

```
drush dispfields blog,page field_thumbnail,field_summary --display=teaser --label=hidden
```

This should result in both the `blog` and `page` nodes having new settings for their `teaser` displays, with both only showing two fields with hidden labels: `field_thumbnail` and `field_summary`.
