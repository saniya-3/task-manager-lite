# Task Manager Lite (WordPress Plugin)

A simple CRUD task manager inside WordPress Admin. Creates a custom DB table and provides an admin page to add, edit, delete, and mark tasks as done.

## Features
- Add/Edit/Delete tasks
- Pending/Done status
- Custom DB table (`wp_tml_tasks`)
- Nonces, sanitization, prepared queries

## Requirements
- WordPress 6.x+
- PHP 7.4+
- MySQL 5.7+

## Installation
1. Download the ZIP from the releases or from your local build.
2. In WordPress Admin go to **Plugins → Add New → Upload Plugin**.
3. Select the ZIP and click **Install Now**, then **Activate**.

## Usage
- Go to **Task Manager** in the WP Admin sidebar.
- Create a task in the form, view and manage all tasks in the table.

## Uninstall
- Deleting the plugin from **Plugins** screen will drop the custom table.
