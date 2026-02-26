# Contact Worker Analysis & Fix

The WordPress.org plugin review team raised concerns about `wp_insert_user` and `wp_update_user` usage in `inc/contacts/contact-worker.php`. Specifically:

1. **Creating users**: Can bypass security checks or create unwanted accounts.
2. **Logging in users**: Can bypass login protection (though this plugin does NOT log users in).
3. **Privilege Escalation**: Risk of modifying admin accounts.

## Analysis

The plugin synchronizes contacts from Bexio (CRM) to WordPress users (WooCommerce customers). This is a core feature, making user creation/updates technically necessary. However, the original implementation lacked safeguards against modifying privileged users (like admins).

## Solution Implemented

I have applied the following security hardening to `inc/contacts/contact-worker.php`:

1.  **Explicit Role Assignment**:

    - Added `'role' => 'customer'` to the user data array.
    - This ensures new users are created with the correct, low-privilege role.
    - Existing users updated by the sync will also have their role set/confirmed as 'customer'. A subscriber might be promoted to customer, but this is generally safe and expected for e-commerce.

2.  **Privilege Protection**:

    - Added a check before `wp_update_user`.
    - If the target detailed user has capabilities `manage_options` (Admin) or `edit_posts` (Editor/Author), the sync **skips** the update and logs a warning.
    - This prevents the sync from accidentally overwriting an administrator's details or changing their role.

3.  **No Login Logic**:
    - Confirmed the plugin uses `wp_insert_user`/`wp_update_user` for data sync only. It does **not** call `wp_signon`, `wp_set_current_user`, or `wp_set_auth_cookie`. It does not log users in.

## Justification for WordPress.org Review Team

You can use the following explanation when replying to the review team:

> "The plugin's core functionality is to synchronize CRM contacts (from Bexio) to WooCommerce customers, allowing orders to be properly attributed. Therefore, programmatically creating and updating users (`wp_insert_user`, `wp_update_user`) is technically necessary for the data sync to work.
>
> We have implemented strict security measures to mitigate risks:
>
> 1. We explicitly set the role to 'customer' for all synced users.
> 2. We added a check to **skip updates** for any user with `manage_options` or `edit_posts` capabilities (Admins/Editors). This ensures privileged accounts are never modified by the sync process.
> 3. The plugin does _not_ log users in (no `wp_signon` or auth cookie setting), it only manages the user database records."
