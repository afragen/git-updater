# WP Dismiss Notice

Add time dismissible admin notices to WordPress.
Fork of https://github.com/w3guy/persist-admin-notices-dismissal

## Instuctions

Initialize the class.

`new \WP_Dismiss_Notice();` in your project.

### Admin notice format.

You must add `data-dismissible='<admin notice identifier>-<timeout>'` to the admin notice div class. `<timeout>` values are from one day '1' to 'forever'. Default timeout is 14 days. The `<admin notice identifier>` should be some unique value based upon the admin notice that you wish to dismiss.

Example using a 14 day dismissible notice.

```html
<div class="notice-warning notice is-dismissible" data-dismissible="my_admin_notice_<hash>-14">...</div>
```
