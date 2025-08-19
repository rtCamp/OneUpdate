# OneUpdate - Installation and Setup Guide

This guide provides detailed instructions for installing and configuring OneUpdate for your WordPress enterprise environment.

## Installation Overview

OneUpdate requires installation on **two types of sites**: one **Governing Site** (central dashboard) and multiple **Brand Sites** (managed sites).

## Step 1: Download and Install Plugin

1. Download the latest OneUpdate plugin from [GitHub Releases](https://github.com/rtCamp/OneUpdate/releases)
2. Upload the plugin files to `/wp-content/plugins/oneupdate/` on both governing and brand sites
3. If installing from source code, run the following commands in the plugin directory:
   ```bash
   composer install && npm install && npm run build:prod
   ```

## Step 2: Setup Governing Site (Central Dashboard)

**The governing site acts as your central control panel to manage all brand sites.**

1. **Activate Plugin:** Go to WordPress Admin → Plugins and activate OneUpdate
2. **Configure Site Type:** Upon activation, select **"Governing Site"** when prompted
3. **Setup Credentials:** Navigate to OneUpdate → Settings and configure:
   - **GitHub Personal Access Token:** Create a token with `repo` permissions for all brand site repositories
   - **S3 Credentials:** (Optional) For private plugin management - Access Key, Secret Key, Bucket Name, and Region

## Step 3: Setup Brand Sites (Managed Sites)

**Each brand site needs OneUpdate installed to receive plugin management commands.**

1. **Activate Plugin:** Go to WordPress Admin → Plugins and activate OneUpdate on each brand site
2. **Configure Site Type:** Upon activation, select **"Brand Site"** when prompted
3. **Generate API Key:** The plugin will generate a unique public key for secure communication
4. **Copy Configuration Details:** Note down:
   - Site Name
   - Site URL  
   - Public API Key
   - GitHub Repository URL (where your brand site code is hosted)

## Step 4: Connect Brand Sites to Governing Site

**Register each brand site with your governing site for centralized management.**

1. **Access Governing Site:** Go to OneUpdate → Settings on your governing site
2. **Add Brand Site:** Click "Add New Site" and enter:
   - **Site Name:** Descriptive name for the brand site
   - **Site URL:** Full URL of the brand site
   - **Public Key:** The API key generated on the brand site
   - **GitHub Repository:** Repository URL for the brand site

## Step 5: Configure GitHub Actions Workflows

**Add the required workflow files to each brand site repository for automated plugin management.**

1. **Create GitHub Actions Directory:** In each brand site repository, create `.github/workflows/` directory if it doesn't exist

2. **Add Workflow Files:** Copy the following files from the plugin's `actions/` directory to your repository:
   - **`oneupdate-pr-creation.yml`** - For managing public WordPress.org plugins
   - **`oneupdate-pr-creation-private.yml`** - For managing private custom plugins

3. **Configure Repository Secrets:** In your GitHub repository settings, add the following secret:
   - **`ONEUPDATE_RTCAMP_TOKEN`** - Your GitHub Personal Access Token

4. **Grant Permissions:** Ensure the GitHub token has the following permissions:
   - `repo` - Full repository access
   - `workflow` - Update GitHub Actions workflows
   - `pull_requests:write` - Create and update pull requests

## Configuration Verification

After completing the installation:

1. **Test Connection:** Verify that brand sites appear in the governing site's dashboard
2. **Check API Communication:** Ensure the governing site can communicate with all brand sites
3. **Validate GitHub Integration:** Test that GitHub Actions workflows can be triggered
4. **S3 Configuration:** (If using private plugins) Verify S3 upload functionality

## Troubleshooting Installation

### Common Installation Issues

#### Plugin Not Showing in Governing
- Verify governing site configuration
- Check network connectivity between sites
- Confirm REST API permissions

#### GitHub Actions Not Working
- Ensure GitHub workflows are properly configured:
  - `oneupdate-pr-creation.yml`
  - `oneupdate-pr-creation-private.yml`
- Verify GitHub PAT token permissions
- Check repository access rights

#### S3 Upload Errors
- Check S3 credentials and bucket permissions
- Verify AWS region configuration
- Ensure bucket allows file uploads

### Getting Help

If you encounter issues during installation:

- **Issues & Bug Reports:** [GitHub Issues](https://github.com/rtCamp/OneUpdate/issues)
- **Feature Requests:** [GitHub Discussions](https://github.com/rtCamp/OneUpdate/discussions)
- **Documentation:** [Project Wiki](https://github.com/rtCamp/OneUpdate/wiki)

## Next Steps

Once installation is complete, refer to the [main README](../README.md) for:
- Usage instructions
- Plugin management features
- Advanced configuration options

---

**Need additional help?** Visit our [GitHub repository](https://github.com/rtCamp/OneUpdate) for the latest updates and community support.