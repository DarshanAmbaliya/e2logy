# Drupal Workflow Engine

A custom Drupal module for creating configurable, multi-step assessment workflows using dynamic fields, conditions, and result rules.

## Demo Videos

- **Backend Workflow Configuration Interface:** [Watch Demo](https://www.awesomescreenshot.com/video/57961295?key=a27a207f4672400682c20cb038912014)
- **Frontend Workflow and Assessment Result:** [Watch Demo](https://www.awesomescreenshot.com/video/57961388?key=4619cc11ba80fd6468fb067375e405f2)
- **Backend Submission Listing and Detail View:** [Watch Demo](https://www.awesomescreenshot.com/video/57961985?key=0d6c5013c5d910497cfadaae1e327664)

## Requirements

- PHP 8.4
- Drupal 11
- Composer
- Docker
- DDEV

## Installation

### 1. Clone the Repository

```bash
git clone <YOUR_GITHUB_REPOSITORY_URL>
cd <YOUR_PROJECT_DIRECTORY>
```

### 2. Start DDEV

```bash
ddev start
```

### 3. Install Dependencies

```bash
ddev composer install
```

### 4. Import the Database

```bash
ddev import-db --file=e2logy-db.sql
```

### 5. Clear Cache

```bash
ddev drush cr
```

### 6. Open the Website

```bash
ddev launch
```

### 7. Enable the Custom Module

If the module is not already enabled:

```bash
ddev drush en workflow_engine -y
ddev drush cr
```

## Alternative Setup (Without Database Backup)

If you do not want to import the `e2logy-db.sql` file, follow these steps:

**1. Install Drush**

```bash
ddev composer require drush/drush
```

**2. Import Configuration into the Database**

```bash
ddev drush cim
```

**Note:** This approach requires an already installed Drupal site and an initialized database. The configuration sync directory must contain the exported Drupal configuration.

## Workflow Engine

Open the workflow builder at:

`<YOUR_SITE_NAME>/admin/structure/assessments`

## Useful Commands

| **Command** | **Description** |
|---|---|
| `ddev start` | Start the project |
| `ddev stop` | Stop the project |
| `ddev restart` | Restart the project |
| `ddev drush cr` | Clear Drupal cache |
| `ddev drush status` | Check Drupal status |
| `ddev drush uli` | Generate a one-time login link |

## Database

The `e2logy-db.sql` file contains the database backup required to restore the existing Drupal project.

**Note:** Importing the database backup will replace the existing local database contents.
