# WordPress.org SVN assets

Files in this folder are uploaded to the **SVN `assets/` directory** on
WordPress.org — they are NOT included in the plugin zip that users download.

## Required files

| File                  | Size          | Purpose                                                         |
| --------------------- | ------------- | --------------------------------------------------------------- |
| `icon-128x128.png`    | 128 × 128 px  | Plugin icon (lo-res)                                            |
| `icon-256x256.png`    | 256 × 256 px  | Plugin icon (hi-res / Retina)                                   |
| `banner-772x250.jpg`  | 772 × 250 px  | Plugin directory banner (lo-res)                                |
| `banner-1544x500.jpg` | 1544 × 500 px | Plugin directory banner (hi-res / Retina, optional)             |
| `screenshot-1.png`    | any           | Settings page (matches README.txt "Screenshots" section item 1) |
| `screenshot-2.png`    | any           | WooCommerce order list with Bexio sync status (item 2)          |

## SVN upload command (once you have the files)

```
svn add assets/icon-256x256.png
svn add assets/banner-772x250.jpg
svn add assets/screenshot-1.png
svn add assets/screenshot-2.png
svn commit -m "Add plugin assets"
```

Screenshots referenced in README.txt must be named `screenshot-N.png`
where N matches the numbered list under `== Screenshots ==`.
