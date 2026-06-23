<?php
/**
 * Help Center — searchable help topics covering all CRM features
 * 
 * Organized into categories matching the sidebar navigation:
 * - Getting Started (dashboard, navigation, user roles)
 * - Leads (create, edit, import, export, convert to contacts)
 * - Contacts & Accounts (create, manage, link to leads)
 * - Pipeline / Deals (manage sales pipeline, deals)
 * - Tasks (create, assign, track tasks)
 * - Proposals & Quotes (create, send, track)
 * - Support Tickets (create, resolve)
 * - Interactions (log calls, meetings, follow-ups)
 * - Reports & Export (analytics, data export)
 * - Email Marketing (campaigns, templates, audiences, SMTP)
 * - Communications (VoIP, WhatsApp, Web Forms)
 * - Business (Products, Automation)
 * - Knowledge Hub (documents, shared resources)
 * - User Management (users, roles, permissions)
 * - Settings (company profile, branding, integrations)
 * - Platform Admin (super admin: companies, plans, billing)
 * 
 * Features:
 * - Live search by keyword, category, or topic title
 * - Category cards with icons and topic counts
 * - Accordion-style expand/collapse for each article
 * - Clean, responsive design matching the CRM's layout
 * 
 * @author Izzy (AI Assistant)
 * @lastupdated 2026-06-23
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireLogin();

$pageTitle = 'Help Center';
$currentPage = 'help';
include __DIR__ . '/../includes/header.php';

// ─── Help Topics Data ──────────────────────────────────────
// Each topic: [id, category, title, icon, content (HTML allowed)]
$helpCategories = [
    'getting_started' => ['icon' => '🚀', 'label' => 'Getting Started', 'color' => '#3b82f6'],
    'leads' => ['icon' => '📊', 'label' => 'Leads', 'color' => '#8b5cf6'],
    'contacts' => ['icon' => '👥', 'label' => 'Contacts & Accounts', 'color' => '#06b6d4'],
    'pipeline' => ['icon' => '🎯', 'label' => 'Pipeline & Deals', 'color' => '#f59e0b'],
    'tasks' => ['icon' => '✅', 'label' => 'Tasks', 'color' => '#10b981'],
    'proposals' => ['icon' => '📄', 'label' => 'Proposals & Quotes', 'color' => '#ec4899'],
    'support' => ['icon' => '🎧', 'label' => 'Support Tickets', 'color' => '#ef4444'],
    'interactions' => ['icon' => '💬', 'label' => 'Interactions', 'color' => '#6366f1'],
    'reports' => ['icon' => '📈', 'label' => 'Reports & Export', 'color' => '#14b8a6'],
    'email_marketing' => ['icon' => '📧', 'label' => 'Email Marketing', 'color' => '#f97316'],
    'communications' => ['icon' => '📞', 'label' => 'Communications', 'color' => '#0ea5e9'],
    'business' => ['icon' => '💼', 'label' => 'Business Tools', 'color' => '#a855f7'],
    'knowledge' => ['icon' => '📚', 'label' => 'Knowledge Hub', 'color' => '#64748b'],
    'users' => ['icon' => '🔐', 'label' => 'User Management', 'color' => '#dc2626'],
    'settings' => ['icon' => '⚙️', 'label' => 'Settings', 'color' => '#6b7280'],
    'platform' => ['icon' => '🏰', 'label' => 'Platform Admin', 'color' => '#7c3aed'],
];

$helpTopics = [
    // ── Getting Started ──
    ['getting_started', 'Navigating the CRM', '
        <p>The sidebar on the left organizes all features into sections:</p>
        <ul>
            <li><strong>Main:</strong> Dashboard, Leads, Contacts, Pipeline, Tasks, Proposals, Support, Interactions, Reports</li>
            <li><strong>Email Marketing:</strong> Campaigns, Templates, Audiences</li>
            <li><strong>Communications:</strong> Web Forms, VoIP Calls, WhatsApp</li>
            <li><strong>Business:</strong> Products, Automation</li>
            <li><strong>Knowledge Hub:</strong> Documents & shared resources</li>
            <li><strong>Admin:</strong> Users, Settings, Platform Admin</li>
        </ul>
        <p>Click any section to expand its pages. The dashboard gives you a quick overview of your key metrics.</p>'],
    ['getting_started', 'Understanding User Roles', '
        <p>The CRM has four user roles with increasing permissions:</p>
        <ul>
            <li><strong>Viewer</strong> — Read-only access to leads, contacts, and reports</li>
            <li><strong>Sales Rep</strong> — Can manage their own leads, contacts, tasks, and interactions</li>
            <li><strong>Sales Manager</strong> — Can manage all leads/contacts in the company, assign leads, view all reports</li>
            <li><strong>Admin</strong> — Full company access including settings, users, and integrations</li>
        </ul>
        <p>Super Admins (platform-level) can manage all companies, plans, and platform settings.</p>'],
    ['getting_started', 'Using the Dashboard', '
        <p>The dashboard provides an at-a-glance overview of your sales performance:</p>
        <ul>
            <li><strong>Stats cards</strong> show total leads, active deals, tasks, and revenue</li>
            <li><strong>Charts</strong> visualize lead trends, conversion rates, and pipeline health</li>
            <li><strong>Recent activity</strong> shows the latest leads and interactions</li>
            <li><strong>Quick actions</strong> let you add new leads or tasks directly</li>
        </ul>
        <p>Sales Reps see only their own data; Managers and Admins see company-wide stats.</p>'],

    // ── Leads ──
    ['leads', 'How to Create a New Lead', '
        <ol>
            <li>Click <strong>Leads</strong> in the sidebar</li>
            <li>Click the <strong>"Add New Lead"</strong> button in the top right</li>
            <li>Fill in the lead details:
                <ul>
                    <li><strong>Company Name</strong> (required) — the organization name</li>
                    <li><strong>Contact Person</strong> (required) — who you\'re dealing with</li>
                    <li><strong>Email & Phone</strong> — use the country code picker to select the flag and dial code, then type the local number</li>
                    <li><strong>Country</strong> — select from the searchable dropdown</li>
                    <li><strong>Lead Source</strong> — where the lead came from (Website, Referral, TikTok, etc.)</li>
                    <li><strong>Status & Priority</strong> — track the lead\'s progress</li>
                </ul>
            </li>
            <li>Click <strong>Save</strong> to create the lead</li>
        </ol>'],
    ['leads', 'How to Edit or Update a Lead', '
        <ol>
            <li>Go to <strong>Leads</strong> in the sidebar</li>
            <li>Click on the lead\'s company name or the <strong>Edit</strong> icon</li>
            <li>Update any fields — all changes save instantly when you click <strong>Save</strong></li>
        </ol>
        <p>You can also update a lead\'s status directly from the leads list using the status dropdown on each row.</p>'],
    ['leads', 'How to Import Leads in Bulk', '
        <ol>
            <li>Go to <strong>Leads</strong> → click <strong>Import</strong> button</li>
            <li>Download the CSV template to see the required column format</li>
            <li>Fill in your leads data in the CSV file (one lead per row)</li>
            <li>Upload the file — the system will validate and import all leads</li>
            <li>Review the import summary for any errors</li>
        </ol>
        <p><strong>Tip:</strong> Make sure phone numbers include country codes (e.g., +1 5551234567) for consistency.</p>'],
    ['leads', 'How to Export Leads', '
        <ol>
            <li>Go to <strong>Export Data</strong> in the sidebar</li>
            <li>Select <strong>Leads</strong> as the export type</li>
            <li>Apply filters if needed (status, date range, assigned to)</li>
            <li>Click <strong>Export</strong> — a CSV file will download</li>
        </ol>
        <p>You can also export from the Leads page using the export button.</p>'],
    ['leads', 'How to Convert a Lead to a Contact', '
        <ol>
            <li>Open the lead you want to convert</li>
            <li>Click the <strong>"Move to Contacts"</strong> button</li>
            <li>The lead\'s data (name, email, phone, company) will be transferred to a new contact</li>
            <li>The original lead is marked as "Converted" and linked to the new contact</li>
        </ol>
        <p>This creates a permanent record in your Contacts while preserving the lead history.</p>'],
    ['leads', 'Filtering and Searching Leads', '
        <p>Use the filter bar at the top of the Leads page to narrow down your list:</p>
        <ul>
            <li><strong>Search</strong> — type any keyword (company name, contact, email, phone)</li>
            <li><strong>Status filter</strong> — New, Contacted, Qualified, Proposal, Won, Lost</li>
            <li><strong>Country filter</strong> — filter by country</li>
            <li><strong>Assigned to</strong> — filter by team member</li>
            <li><strong>Date range</strong> — filter by creation or update date</li>
        </ul>
        <p>Combine multiple filters for precise results. Click "Clear" to reset all filters.</p>'],
    ['leads', 'Assigning Leads to Team Members', '
        <ol>
            <li>Open the lead you want to reassign</li>
            <li>Scroll to the <strong>Assigned To</strong> field</li>
            <li>Select the team member from the dropdown</li>
            <li>Save the lead</li>
        </ol>
        <p>Managers can also bulk-assign leads: select multiple leads using the checkboxes, then choose "Bulk Assign" from the actions menu.</p>'],

    // ── Contacts & Accounts ──
    ['contacts', 'How to Create a New Contact', '
        <ol>
            <li>Go to <strong>Contacts</strong> in the sidebar</li>
            <li>Click <strong>"New Contact"</strong></li>
            <li>Fill in contact details (first name, last name, email, phone with country code, country)</li>
            <li>Optionally link to an existing Account (company)</li>
            <li>Save the contact</li>
        </ol>'],
    ['contacts', 'How to Create a New Account', '
        <ol>
            <li>Go to <strong>Contacts</strong> → click the <strong>Accounts</strong> tab</li>
            <li>Click <strong>"New Account"</strong></li>
            <li>Enter the account name, website, phone, and address</li>
            <li>Save the account</li>
        </ol>
        <p>Accounts group multiple contacts under the same organization. You can link contacts to accounts for better organization.</p>'],
    ['contacts', 'Linking Contacts to Accounts', '
        <p>When creating or editing a contact, select an existing account from the <strong>Account</strong> dropdown. This links the contact to that organization, so you can see all contacts within an account.</p>'],
    ['contacts', 'Managing Contact Status', '
        <p>Contacts can have these statuses:</p>
        <ul>
            <li><strong>Active</strong> — Currently engaged</li>
            <li><strong>Inactive</strong> — No current engagement</li>
            <li><strong>Do Not Contact</strong> — Opted out of communications</li>
        </ul>
        <p>Update a contact\'s status from their detail page. "Do Not Contact" contacts are excluded from email campaigns.</p>'],

    // ── Pipeline & Deals ──
    ['pipeline', 'Understanding the Sales Pipeline', '
        <p>The Pipeline page shows all your deals organized by stage. Each deal card shows the deal value, contact, and expected close date.</p>
        <p>Default stages: <strong>Lead → Qualified → Proposal → Negotiation → Won / Lost</strong></p>
        <p>Drag deals between stages to update their status. The pipeline value is calculated automatically.</p>'],
    ['pipeline', 'How to Create a New Deal', '
        <ol>
            <li>Go to <strong>Pipeline</strong> in the sidebar</li>
            <li>Click <strong>"New Deal"</strong></li>
            <li>Enter the deal title, value, expected close date</li>
            <li>Link to a lead or contact (optional)</li>
            <li>Select the initial stage</li>
            <li>Save the deal</li>
        </ol>'],
    ['pipeline', 'Moving Deals Through Stages', '
        <p>You can move deals between stages in two ways:</p>
        <ol>
            <li><strong>Drag and drop</strong> — click a deal card and drag it to the next stage column</li>
            <li><strong>Edit the deal</strong> — open the deal and change the stage dropdown</li>
        </ol>'],

    // ── Tasks ──
    ['tasks', 'How to Create a Task', '
        <ol>
            <li>Go to <strong>Tasks</strong> in the sidebar</li>
            <li>Click <strong>"New Task"</strong></li>
            <li>Enter the task title, description, due date, and priority</li>
            <li>Assign to a team member</li>
            <li>Link to a lead or contact (optional)</li>
            <li>Save the task</li>
        </ol>'],
    ['tasks', 'Tracking Task Status', '
        <p>Tasks have three statuses: <strong>Pending</strong>, <strong>In Progress</strong>, and <strong>Completed</strong>. Click the status badge on any task to cycle through statuses. Overdue tasks are highlighted in red.</p>'],

    // ── Proposals & Quotes ──
    ['proposals', 'How to Create a Proposal', '
        <ol>
            <li>Go to <strong>Proposals</strong> in the sidebar</li>
            <li>Click <strong>"New Proposal"</strong></li>
            <li>Select the client (lead or contact)</li>
            <li>Add line items (products/services with quantities and prices)</li>
            <li>Set tax rate and discounts if applicable</li>
            <li>Save and optionally send to the client</li>
        </ol>'],
    ['proposals', 'How to Create a Quote', '
        <p>Quotes work the same way as proposals but are typically used earlier in the sales process. Go to Quotes → New Quote, fill in the details, and generate a PDF to share with the client.</p>'],

    // ── Support Tickets ──
    ['support', 'How to Create a Support Ticket', '
        <ol>
            <li>Go to <strong>Support</strong> in the sidebar</li>
            <li>Click <strong>"New Ticket"</strong></li>
            <li>Enter the subject, description, and priority (Low, Medium, High, Urgent)</li>
            <li>Link to a contact or account if applicable</li>
            <li>Save the ticket</li>
        </ol>'],
    ['support', 'Resolving Tickets', '
        <p>Open a ticket and update its status: <strong>Open → In Progress → Resolved → Closed</strong>. Add comments to track the resolution process. The ticket requester is notified of status changes.</p>'],

    // ── Interactions ──
    ['interactions', 'Logging Interactions', '
        <p>Interactions track all communication with leads and contacts:</p>
        <ol>
            <li>Open a lead or contact</li>
            <li>Click <strong>"Add Interaction"</strong></li>
            <li>Select the type: Call, Email, Meeting, Follow-up, Note, or Visit</li>
            <li>Add a subject and notes</li>
            <li>Set a follow-up date if needed</li>
            <li>Save the interaction</li>
        </ol>'],

    // ── Reports & Export ──
    ['reports', 'Viewing Reports and Analytics', '
        <p>The <strong>Reports</strong> page provides insights into your sales performance:</p>
        <ul>
            <li><strong>Lead conversion</strong> — how many leads become customers</li>
            <li><strong>Sales by source</strong> — which channels produce the best leads</li>
            <li><strong>Sales by country</strong> — geographic distribution</li>
            <li><strong>Team performance</strong> — activity by team member</li>
            <li><strong>Revenue tracking</strong> — deals won and pipeline value</li>
        </ul>
        <p>Use date range filters to focus on specific periods.</p>'],
    ['reports', 'Exporting Your Data', '
        <ol>
            <li>Go to <strong>Export Data</strong> in the sidebar</li>
            <li>Choose what to export: Leads, Contacts, Deals, or Interactions</li>
            <li>Apply filters (date range, status, etc.)</li>
            <li>Click <strong>Export</strong> to download a CSV file</li>
        </ol>
        <p>Exports respect your role permissions — Sales Reps can only export their own data.</p>'],

    // ── Email Marketing ──
    ['email_marketing', 'How to Configure SMTP Settings', '
        <ol>
            <li>Go to <strong>Settings</strong> → <strong>SMTP & Email</strong> tab</li>
            <li>Enter your SMTP server details:
                <ul>
                    <li><strong>SMTP Host</strong> — e.g., smtp.gmail.com or smtp.mailgun.org</li>
                    <li><strong>SMTP Port</strong> — typically 587 (TLS) or 465 (SSL)</li>
                    <li><strong>SMTP Username</strong> — your email/login</li>
                    <li><strong>SMTP Password</strong> — your email password or app-specific password</li>
                    <li><strong>Encryption</strong> — TLS (recommended) or SSL</li>
                </ul>
            </li>
            <li>Set the <strong>From Name</strong> and <strong>From Email</strong></li>
            <li>Configure batch settings to avoid rate limits:
                <ul>
                    <li><strong>Batch Size</strong> — emails per batch (default: 50)</li>
                    <li><strong>Batch Delay</strong> — seconds between batches (default: 2)</li>
                </ul>
            </li>
            <li>Click <strong>Save Settings</strong></li>
        </ol>
        <p><strong>Tip:</strong> For Gmail, use an App Password (not your regular password) and set encryption to TLS on port 587.</p>'],
    ['email_marketing', 'How to Set Up Email Integration (Office 365)', '
        <ol>
            <li>Go to <strong>Settings</strong> → <strong>Email Integration</strong> tab</li>
            <li>If you\'re a super admin, enter your <strong>Microsoft Azure App credentials</strong>:
                <ul>
                    <li>Client ID, Tenant ID, and Client Secret from Azure</li>
                    <li>Redirect URI: <code>https://app.funl.online/api/microsoft-callback.php</code></li>
                </ul>
            </li>
            <li>Save settings, then click <strong>Connect Office 365</strong></li>
            <li>Sign in with your Microsoft account and grant permissions</li>
        </ol>
        <p>Once connected, emails sent from the CRM use your Office 365 account directly.</p>'],
    ['email_marketing', 'How to Create an Email Campaign', '
        <ol>
            <li>Go to <strong>Email Campaigns</strong> in the sidebar</li>
            <li>Click <strong>"New Campaign"</strong></li>
            <li>Choose a template or start from scratch</li>
            <li>Set the campaign name, subject line, and sender details</li>
            <li>Select an audience (email list) to send to</li>
            <li>Design your email using the drag-and-drop builder</li>
            <li>Preview and test send</li>
            <li>Schedule or send immediately</li>
        </ol>'],
    ['email_marketing', 'How to Create Email Templates', '
        <ol>
            <li>Go to <strong>Templates</strong> in the sidebar</li>
            <li>Click <strong>"New Template"</strong></li>
            <li>Design your template using the email builder or paste HTML</li>
            <li>Use merge tags like <code>{{first_name}}</code>, <code>{{company_name}}</code> for personalization</li>
            <li>Save the template for reuse in campaigns</li>
        </ol>'],
    ['email_marketing', 'How to Build an Email Audience List', '
        <ol>
            <li>Go to <strong>Email Audiences</strong> in the sidebar</li>
            <li>Click <strong>"New List"</strong></li>
            <li>Choose how to populate:
                <ul>
                    <li><strong>From leads</strong> — filter leads by status, source, or country</li>
                    <li><strong>From contacts</strong> — filter by status or account</li>
                    <li><strong>Manual entry</strong> — paste email addresses</li>
                </ul>
            </li>
            <li>Save the list</li>
        </ol>
        <p>Contacts marked "Do Not Contact" are automatically excluded from audience lists.</p>'],
    ['email_marketing', 'Using the Email Builder', '
        <p>The drag-and-drop email builder lets you design professional emails:</p>
        <ul>
            <li><strong>Drag blocks</strong> — text, images, buttons, dividers, spacers</li>
            <li><strong>Edit inline</strong> — click any block to edit its content</li>
            <li><strong>Merge tags</strong> — use <code>{{first_name}}</code>, <code>{{last_name}}</code>, <code>{{company_name}}</code> for personalization</li>
            <li><strong>Preview</strong> — switch between desktop and mobile views</li>
            <li><strong>Test send</strong> — send a preview to yourself before launching</li>
        </ul>'],

    // ── Communications ──
    ['communications', 'How to Create a Web Form', '
        <ol>
            <li>Go to <strong>Web Forms</strong> in the sidebar</li>
            <li>Click <strong>"New Form"</strong></li>
            <li>Configure form fields (name, email, phone, message, custom fields)</li>
            <li>Set the lead assignment rule (round-robin or specific user)</li>
            <li>Save and get the embed code</li>
            <li>Paste the embed code on your website</li>
        </ol>
        <p>Form submissions automatically create leads in your CRM. They\'re assigned based on your rules.</p>'],
    ['communications', 'Using VoIP Calls', '
        <p>The CRM integrates with Twilio for VoIP calling:</p>
        <ol>
            <li>Go to <strong>Settings</strong> → <strong>VoIP & WhatsApp</strong> tab</li>
            <li>Enter your Twilio credentials (Account SID, Auth Token, Phone Number)</li>
            <li>Save settings</li>
            <li>Use the VoIP Dashboard to make and receive calls</li>
            <li>All calls are logged automatically and can be reviewed</li>
        </ol>'],
    ['communications', 'Using WhatsApp Integration', '
        <p>The CRM connects with WhatsApp Business API:</p>
        <ol>
            <li>Configure WhatsApp in <strong>Settings</strong> → <strong>VoIP & WhatsApp</strong></li>
            <li>Use the WhatsApp Dashboard to send and receive messages</li>
            <li>Link incoming WhatsApp messages to leads</li>
            <li>Create WhatsApp message templates for quick replies</li>
        </ol>'],

    // ── Business Tools ──
    ['business', 'How to Add Products', '
        <ol>
            <li>Go to <strong>Products</strong> in the sidebar</li>
            <li>Click <strong>"New Product"</strong></li>
            <li>Enter the product name, description, price, and tax rate</li>
            <li>Save the product</li>
        </ol>
        <p>Products can be added to proposals and quotes as line items.</p>'],
    ['business', 'How to Create Automation Rules', '
        <p>Automation rules trigger actions automatically based on conditions:</p>
        <ol>
            <li>Go to <strong>Automation</strong> in the sidebar</li>
            <li>Click <strong>"New Rule"</strong></li>
            <li>Choose a trigger:
                <ul>
                    <li><strong>Lead created</strong> — when a new lead is added</li>
                    <li><strong>Status changed</strong> — when a lead\'s status changes</li>
                    <li><strong>Task completed</strong> — when a task is marked done</li>
                </ul>
            </li>
            <li>Set conditions (e.g., "Lead source is Website")</li>
            <li>Define the action:
                <ul>
                    <li><strong>Assign to user</strong> — auto-assign the lead</li>
                    <li><strong>Change status</strong> — update the lead\'s status</li>
                    <li><strong>Send email</strong> — trigger an email notification</li>
                    <li><strong>Create task</strong> — auto-create a follow-up task</li>
                </ul>
            </li>
            <li>Activate the rule</li>
        </ol>'],

    // ── Knowledge Hub ──
    ['knowledge', 'Using the Knowledge Hub', '
        <p>The Knowledge Hub is where your team shares resources:</p>
        <ul>
            <li>Admins and Sales Managers can add <strong>link cards</strong> — each card has a title, description, category, and external link</li>
            <li>Categories: General, Sales, Marketing, Training, Legal, Other</li>
            <li>All team members can browse and click cards to open resources in a new tab</li>
            <li>Link cards save server space — no file uploads needed</li>
        </ul>
        <p>To add a resource: click "Add Card", fill in the title and link URL, choose a category, and save.</p>'],

    // ── User Management ──
    ['users', 'How to Add a New User', '
        <ol>
            <li>Go to <strong>Users</strong> in the sidebar (Admin only)</li>
            <li>Click <strong>"Add User"</strong></li>
            <li>Enter the user\'s name, email, username, and password</li>
            <li>Assign a role: Viewer, Sales Rep, Sales Manager, or Admin</li>
            <li>Save — the user will receive an email verification link</li>
        </ol>
        <p>New users must verify their email before they can log in.</p>'],
    ['users', 'Managing User Roles and Permissions', '
        <p>Admins can change a user\'s role at any time:</p>
        <ol>
            <li>Go to <strong>Users</strong></li>
            <li>Click <strong>Edit</strong> on the user</li>
            <li>Change the role from the dropdown</li>
            <li>Save</li>
        </ol>
        <p>Role changes take effect immediately on the user\'s next page load.</p>'],

    // ── Settings ──
    ['settings', 'Configuring Company Profile', '
        <ol>
            <li>Go to <strong>Settings</strong> → <strong>Company Profile</strong> tab</li>
            <li>Update your company name, email, phone (with country code), website, and address</li>
            <li>Save changes</li>
        </ol>'],
    ['settings', 'Customizing App Branding', '
        <ol>
            <li>Go to <strong>Settings</strong> → <strong>App Branding</strong> tab</li>
            <li>Upload your company logo (recommended: 200×40px PNG)</li>
            <li>Upload a favicon</li>
            <li>Set a custom app name that appears in the sidebar</li>
            <li>Save — the branding appears across the CRM for all users</li>
        </ol>'],
    ['settings', 'Adding Custom Lead Fields', '
        <p>Custom fields let you capture additional information on leads:</p>
        <ol>
            <li>Go to <strong>Settings</strong> → <strong>Custom Lead Fields</strong> tab</li>
            <li>Click <strong>"Add Field"</strong></li>
            <li>Choose a field type: Text, Number, Date, Dropdown, or Checkbox</li>
            <li>For dropdowns, add the options</li>
            <li>Set whether it\'s required</li>
            <li>Save — the field appears on the lead form automatically</li>
        </ol>'],
    ['settings', 'Adding Tracking Codes (Pixels)', '
        <p>Super Admins can inject tracking scripts (Google Analytics, Meta Pixel, GTM) into all CRM pages:</p>
        <ol>
            <li>Go to <strong>Settings</strong> → <strong>Pixels & Tracking</strong> tab</li>
            <li>Paste your tracking code in the <strong>Header Code</strong> field (for <code>&lt;head&gt;</code> tags)</li>
            <li>Paste noscript fallbacks in the <strong>Body Code</strong> field (for after <code>&lt;body&gt;</code> tag)</li>
            <li>Save settings — the scripts are injected on all client-facing pages</li>
        </ol>'],

    // ── Platform Admin ──
    ['platform', 'Managing Companies (Super Admin)', '
        <p>Platform Admins can manage all companies on the platform:</p>
        <ol>
            <li>Go to <strong>Platform Admin</strong> in the sidebar</li>
            <li>View all companies in the table</li>
            <li>Create new companies with the <strong>"Add Company"</strong> button</li>
            <li>Manage company status (Active, Suspended)</li>
            <li>Delete companies (removes all data)</li>
        </ol>'],
    ['platform', 'Managing Subscription Plans', '
        <p>Super Admins can configure subscription plans:</p>
        <ol>
            <li>Go to <strong>Platform Admin</strong> → scroll to <strong>Plans & Pricing</strong></li>
            <li>Click <strong>"Add Plan"</strong> or <strong>"Edit"</strong> on an existing plan</li>
            <li>Configure: name, key, max users, monthly/yearly price, extra user price</li>
            <li>Activate or deactivate plans</li>
        </ol>'],
    ['platform', 'Configuring Payment Gateway', '
        <p>Set up Network International payment gateway for subscription billing:</p>
        <ol>
            <li>Go to <strong>Platform Admin</strong> → <strong>Payment Gateway</strong> section</li>
            <li>Enter your NI API credentials (Client ID, Client Secret, Terminal ID)</li>
            <li>Set the API version</li>
            <li>Save — companies can now subscribe and pay through the CRM</li>
        </ol>'],
    ['platform', 'Platform Settings', '
        <p>Super Admins can configure platform-wide settings:</p>
        <ul>
            <li><strong>Super Admin users</strong> — manage who has platform-level access</li>
            <li><strong>Payment gateway</strong> — Network International credentials</li>
            <li><strong>Plans & Pricing</strong> — subscription tiers and pricing</li>
            <li><strong>Danger Zone</strong> — delete companies and all associated data</li>
        </ul>'],
];
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Help Center</h1>
        <p class="text-muted" style="margin-top:4px;">Find guides, tutorials, and answers to common questions</p>
    </div>
</div>

<!-- Search Bar -->
<div style="margin-bottom:24px;">
    <div style="position:relative;max-width:600px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position:absolute;left:16px;top:50%;transform:translateY(-50%);color:#9ca3af;pointer-events:none;">
            <circle cx="11" cy="11" r="8"/>
            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="text" id="helpSearch" class="form-control" placeholder="Search help topics..." 
               style="padding:12px 16px 12px 48px;font-size:15px;width:100%;"
               oninput="filterHelpTopics()">
    </div>
</div>

<!-- Category Cards -->
<div id="helpCategories" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(220px, 1fr));gap:12px;margin-bottom:32px;">
    <?php foreach ($helpCategories as $catKey => $cat): 
        $topicCount = count(array_filter($helpTopics, fn($t) => $t[0] === $catKey));
    ?>
    <div class="help-category-card" data-category="<?php echo $catKey; ?>" 
         onclick="scrollToCategory('<?php echo $catKey; ?>')"
         style="border:1px solid var(--color-border);border-radius:12px;padding:16px;cursor:pointer;transition:all 0.2s;background:var(--color-bg);">
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:22px;background:<?php echo $cat['color']; ?>15;">
                <?php echo $cat['icon']; ?>
            </div>
            <div>
                <div style="font-weight:600;font-size:14px;"><?php echo htmlspecialchars($cat['label']); ?></div>
                <div style="font-size:12px;color:var(--color-text-muted);"><?php echo $topicCount; ?> article<?php echo $topicCount !== 1 ? 's' : ''; ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Help Topics -->
<div id="helpTopics">
    <?php foreach ($helpCategories as $catKey => $cat): 
        $topics = array_filter($helpTopics, fn($t) => $t[0] === $catKey);
        if (empty($topics)) continue;
    ?>
    <div class="help-category-section" data-category="<?php echo $catKey; ?>" style="margin-bottom:32px;">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
            <div style="width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;background:<?php echo $cat['color']; ?>15;">
                <?php echo $cat['icon']; ?>
            </div>
            <h2 style="font-size:18px;font-weight:600;margin:0;"><?php echo htmlspecialchars($cat['label']); ?></h2>
            <span style="font-size:13px;color:var(--color-text-muted);"><?php echo count($topics); ?> articles</span>
        </div>

        <div style="display:flex;flex-direction:column;gap:8px;">
            <?php foreach ($topics as $idx => $topic): 
                $topicId = $catKey . '_' . $idx;
            ?>
            <div class="help-article" data-topic-id="<?php echo $topicId; ?>" 
                 data-search-text="<?php echo htmlspecialchars(strtolower($cat['label'] . ' ' . $topic[1] . ' ' . strip_tags($topic[2]))); ?>"
                 style="border:1px solid var(--color-border);border-radius:10px;overflow:hidden;transition:border-color 0.2s;">
                <button type="button" 
                        onclick="toggleArticle('<?php echo $topicId; ?>')"
                        style="width:100%;text-align:left;padding:14px 18px;background:none;border:none;cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:12px;font-size:14px;font-weight:500;color:var(--color-text);">
                    <span><?php echo htmlspecialchars($topic[1]); ?></span>
                    <svg class="help-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;transition:transform 0.2s;color:var(--color-text-muted);">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                </button>
                <div class="help-content" id="content_<?php echo $topicId; ?>" 
                     style="display:none;padding:0 18px 16px;font-size:14px;line-height:1.7;color:var(--color-text-secondary);">
                    <?php echo $topic[2]; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- No Results Message -->
<div id="helpNoResults" style="display:none;text-align:center;padding:60px 20px;">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="1.5" style="opacity:0.5;margin-bottom:16px;">
        <circle cx="11" cy="11" r="8"/>
        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
    </svg>
    <h3 style="color:var(--color-text-secondary);margin-bottom:8px;">No articles found</h3>
    <p style="color:var(--color-text-muted);">Try different keywords or browse categories above</p>
</div>

<script>
/**
 * Toggle article expansion — accordion behavior
 * @param {string} topicId - The unique topic identifier
 */
function toggleArticle(topicId) {
    var content = document.getElementById('content_' + topicId);
    var chevron = document.querySelector('[data-topic-id="' + topicId + '"] .help-chevron');
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        chevron.style.transform = 'rotate(180deg)';
    } else {
        content.style.display = 'none';
        chevron.style.transform = 'rotate(0deg)';
    }
}

/**
 * Scroll to a specific category section
 * @param {string} categoryKey - The category identifier
 */
function scrollToCategory(categoryKey) {
    var section = document.querySelector('[data-category="' + categoryKey + '"].help-category-section');
    if (section) {
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        // Briefly highlight the section
        section.style.transition = 'background 0.3s';
        section.style.background = 'var(--color-bg-secondary, #f9fafb)';
        setTimeout(function() { section.style.background = ''; }, 1000);
    }
}

/**
 * Filter help topics by search query
 * Searches across category names, topic titles, and content
 */
function filterHelpTopics() {
    var query = document.getElementById('helpSearch').value.toLowerCase().trim();
    var articles = document.querySelectorAll('.help-article');
    var sections = document.querySelectorAll('.help-category-section');
    var categoryCards = document.querySelectorAll('.help-category-card');
    var noResults = document.getElementById('helpNoResults');
    var topicsContainer = document.getElementById('helpTopics');
    var categoriesContainer = document.getElementById('helpCategories');
    var visibleCount = 0;

    if (!query) {
        // Reset: show everything, close all articles
        articles.forEach(function(a) { a.style.display = ''; });
        sections.forEach(function(s) { s.style.display = ''; });
        categoryCards.forEach(function(c) { c.style.display = ''; });
        categoriesContainer.style.display = '';
        noResults.style.display = 'none';
        // Close all expanded articles
        articles.forEach(function(a) {
            var content = a.querySelector('.help-content');
            var chevron = a.querySelector('.help-chevron');
            content.style.display = 'none';
            chevron.style.transform = 'rotate(0deg)';
        });
        return;
    }

    // Hide category cards during search
    categoriesContainer.style.display = 'none';

    articles.forEach(function(article) {
        var searchText = article.getAttribute('data-search-text');
        if (searchText && searchText.indexOf(query) !== -1) {
            article.style.display = '';
            // Auto-expand matching articles
            var content = article.querySelector('.help-content');
            var chevron = article.querySelector('.help-chevron');
            content.style.display = 'block';
            chevron.style.transform = 'rotate(180deg)';
            visibleCount++;
        } else {
            article.style.display = 'none';
        }
    });

    // Hide sections with no visible articles
    sections.forEach(function(section) {
        var visible = section.querySelectorAll('.help-article:not([style*="display: none"])');
        section.style.display = visible.length > 0 ? '' : 'none';
    });

    // Show/hide no results message
    noResults.style.display = visibleCount === 0 ? 'block' : 'none';
    topicsContainer.style.display = visibleCount === 0 ? 'none' : '';
}

// Keyboard shortcut: "/" to focus search
document.addEventListener('keydown', function(e) {
    if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        document.getElementById('helpSearch').focus();
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>