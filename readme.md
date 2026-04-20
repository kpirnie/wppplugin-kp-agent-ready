# KP Agent Ready

[![GitHub Issues](https://img.shields.io/github/issues/kpirnie/wppplugin-kp-agent-ready?style=for-the-badge&logo=github&color=006400&logoColor=white&labelColor=000)](https://github.com/kpirnie/wppplugin-kp-agent-ready/issues)
![Latest Release](https://img.shields.io/github/v/release/kpirnie/wppplugin-kp-agent-ready?label=release&style=for-the-badge&logoColor=white&labelColor=000)
[![License: MIT](https://img.shields.io/badge/License-MIT-orange.svg?style=for-the-badge&logo=opensourceinitiative&logoColor=white&labelColor=000)](LICENSE)
[![PHP](https://img.shields.io/badge/Up%20To-php8.4-777BB4?logo=php&logoColor=white&style=for-the-badge&labelColor=000)](https://php.net)
[![WordPress](https://img.shields.io/badge/Min.%20WP-6.8-3858e9?logo=wordpress&logoColor=white&style=for-the-badge&labelColor=000)](https://php.net)
[![Kevin Pirnie](https://img.shields.io/badge/-KevinPirnie.com-000d2d?style=for-the-badge&labelColor=000&logoColor=white&logo=data:image/svg%2Bxml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSJ3aGl0ZSIgc3Ryb2tlLXdpZHRoPSIxLjgiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCI+CiAgPGNpcmNsZSBjeD0iMTIiIGN5PSIxMiIgcj0iMTAiLz4KICA8ZWxsaXBzZSBjeD0iMTIiIGN5PSIxMiIgcng9IjQuNSIgcnk9IjEwIi8+CiAgPGxpbmUgeDE9IjIiIHkxPSIxMiIgeDI9IjIyIiB5Mj0iMTIiLz4KICA8bGluZSB4MT0iNC41IiB5MT0iNi41IiB4Mj0iMTkuNSIgeTI9IjYuNSIvPgogIDxsaW5lIHgxPSI0LjUiIHkxPSIxNy41IiB4Mj0iMTkuNSIgeTI9IjE3LjUiLz4KPC9zdmc+Cg==)](https://kevinpirnie.com/)

* **Contributors:** kpirnie
* **Tags:** ai, agents, discovery, well-known, mcp, oauth, openid, agent-skills, webmcp, markdown
* **Requires at least:** 6.8
* **Tested up to:** 7.0
* **Requires PHP:** 8.2
* **Stable tag:** 1.0.76
* **License:** MIT
* **License URI:** https://opensource.org/licenses/MIT

Make your WordPress site discoverable and usable by AI agents. Implements the emerging suite of agent-readiness standards — all configurable from the WordPress admin.

---

## Description

AI agents — tools like ChatGPT, Claude, Copilot, and the growing ecosystem of autonomous AI systems — are increasingly being pointed at websites to gather information, interact with APIs, and perform tasks on behalf of users. For a site to work well with these agents, it needs to speak the right language: publishing structured discovery files, declaring its capabilities, and responding to agent-specific requests in the right format.

**KP Agent Ready** handles all of that for your WordPress site. It implements the current set of agent-readiness standards maintained at [isitagentready.com](https://isitagentready.com) and the broader AI agent ecosystem — without requiring you to manually create files, edit server configs, or touch a line of code. Everything is managed from a dedicated settings page in the WordPress admin.

### What It Does

#### RFC 8288 Link Response Headers
Every response your site sends will include `Link` headers pointing agents to your API catalog, agent skills index, and MCP server card. This is how agents find your discovery documents without having to guess URLs.

#### API Catalog — `/.well-known/api-catalog`
Publishes a machine-readable catalog of your site's APIs in the `application/linkset+json` format defined by [RFC 9727](https://www.rfc-editor.org/rfc/rfc9727). Each entry in the catalog can point to an OpenAPI specification, human-readable documentation, and a health/status endpoint. You build the entries through a repeater in the settings — no JSON editing required. If you haven't configured any entries yet, the plugin serves a sensible fallback automatically so the endpoint is always valid.

#### Agent Skills Index — `/.well-known/agent-skills/index.json`
Publishes a skills discovery index per the [Agent Skills Discovery RFC v0.2.0](https://github.com/cloudflare/agent-skills-discovery-rfc). This tells agents what your site can *do* — search your blog, browse your portfolio, submit a contact form, etc.

Skills are built from three sources:
- **Blog articles** — a search skill and a browse skill, toggled with a single switch
- **Custom post types** — any public CPT registered on your site appears as a checkbox; tick it and it gets a browse skill pointing to its archive
- **Manual entries** — a repeater lets you define any additional skill with a name, type, description, URL (or a page picker), and an optional sha256 digest

#### MCP Server Card — `/.well-known/mcp/server-card.json`
Publishes an [MCP Server Card](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127) (SEP-1649) identifying your site to Model Context Protocol clients. Configurable name, version, description, transport endpoint, and a capability list you build in the settings. If you don't have an MCP server running yet, just leave the transport blank — the card is still valid and useful for discovery.

#### OAuth / OIDC Discovery — `/.well-known/openid-configuration` or `/.well-known/oauth-authorization-server`
If your site exposes protected APIs that require authentication, this feature publishes the discovery metadata agents need to figure out how to authenticate. Supports both [OpenID Connect Discovery 1.0](http://openid.net/specs/openid-connect-discovery-1_0.html) and [RFC 8414](https://www.rfc-editor.org/rfc/rfc8414) (OAuth 2.0 Authorization Server Metadata). Choose which protocol you're using, fill in your endpoints, and check off which grant types, response types, token auth methods, and scopes you support — all from the admin.

This feature is **disabled by default** since most WordPress sites don't run their own OAuth server. Enable it in the Features tab only if you have the infrastructure in place.

#### OAuth Protected Resource Metadata — `/.well-known/oauth-protected-resource`
Complements the OAuth/OIDC feature by publishing [RFC 9728](https://www.rfc-editor.org/rfc/rfc9728) Protected Resource Metadata. This tells agents which authorization servers can issue tokens for your resource, which bearer methods you accept, and which scopes are available. Also **disabled by default**.

#### robots.txt Content Signals
Appends [Content Signals](https://contentsignals.org/) directives to your `robots.txt` file, declaring your preferences for how AI systems may use your content:
- **ai-train** — whether AI companies may use your content to train models
- **search** — whether search engines may index your content
- **ai-input** — whether AI retrieval systems (RAG) may use your content as input

Each preference is set independently with a simple Yes/No toggle.

#### Markdown Negotiation
When an AI agent sends a request with the `Accept: text/markdown` header, the plugin intercepts the response for singular posts and pages and returns a clean Markdown version of the content with `Content-Type: text/markdown`. Regular browser requests are unaffected — they continue to receive HTML as normal.

#### WebMCP
Injects a small JavaScript snippet into your page footer that calls `navigator.modelContext.provideContext()`, exposing your site's key actions as tools to AI agents running in the browser (per the emerging [WebMCP](https://webmachinelearning.github.io/webmcp/) standard). Built-in tools include blog search, portfolio navigation, and contact page navigation — each individually toggleable.

---

## Installation

> **Note:** This plugin is distributed via [GitHub](https://github.com/kpirnie/wppplugin-kp-agent-ready) and includes its Composer dependencies in the repository. No separate Composer step is required when installing a downloaded release.

### From GitHub

1. Go to [Releases](https://github.com/kpirnie/wppplugin-kp-agent-ready/releases) and download the latest `.zip`
2. In your WordPress admin, go to **Plugins → Add New Plugin → Upload Plugin**
3. Upload the zip and click **Install Now**
4. Click **Activate Plugin**
5. Go to **Settings → Permalinks** and click **Save Changes** — this is required to register the `/.well-known/` URL rules

### Manual (FTP / SSH)

1. Download or clone the repository into `wp-content/plugins/kp-agent-ready/`
2. Activate the plugin from **Plugins → Installed Plugins**
3. Go to **Settings → Permalinks** and click **Save Changes**

---

## Configuration

After activation, an **Agent Ready** menu item appears in the WordPress admin sidebar. It has the following tabs, each also available as a direct submenu link.

---

### Features Tab

This is where you enable and disable each major feature. All features except blog-related ones are enabled by default. The two OAuth features are **disabled by default** — only enable them if your site actually runs an OAuth or OpenID Connect server.

| Feature | What It Controls |
|---------|-----------------|
| RFC 8288 Link Headers | Sends `Link` headers on all responses |
| Content Signals | Adds `Content-Signal` to robots.txt |
| Markdown Negotiation | Serves Markdown to agents that request it |
| WebMCP | Injects the WebMCP tool context script |
| OAuth / OIDC Discovery | Serves `/.well-known/openid-configuration` or `/.well-known/oauth-authorization-server` |
| OAuth Protected Resource | Serves `/.well-known/oauth-protected-resource` |

---

### API Catalog Tab

Build the entries that appear in `/.well-known/api-catalog`. Click **Add Entry** to add a row.

Each entry has:
- **Anchor URL** *(required)* — the base URL of the API this entry describes, e.g. `https://yoursite.com/wp-json/`
- **service-desc** — URL of your OpenAPI/Swagger spec file
- **service-doc** — URL of your human-readable API documentation, or pick a WordPress page from the dropdown
- **status** — URL of a health/status endpoint

If you add no entries, the plugin automatically serves a minimal valid catalog pointing to your site so the endpoint is never broken.

---

### Agent Skills Tab

Controls what appears in `/.well-known/agent-skills/index.json`.

**Blog / Articles**
A single toggle. When on, two skills are automatically added: one for searching your blog, one for browsing all articles.

**Custom Post Types**
Any public CPT registered on your site (by your theme or other plugins) appears here as a checkbox. Checking it adds a browse skill for that CPT pointing to its archive page.

**Custom Skills**
A repeater where you can manually define any skill. Fields:
- **Name** — a short identifier (will be slugified)
- **Type** — Browse, Search, Form, Action, or API
- **Description** — what this skill does, in plain language
- **URL** — the skill's target URL
- **Or select a page** — pick a WordPress page instead of typing a URL
- **sha256 Digest** — optional, for the Agent Skills RFC compliance digest field

---

### Content Signals Tab

Set your AI content usage preferences. These are written into your `robots.txt` as a `Content-Signal` directive.

- **AI Training (ai-train)** — Yes/No — may AI companies use your content to train models?
- **Search Indexing (search)** — Yes/No — may search engines index your content?
- **AI RAG Input (ai-input)** — Yes/No — may AI retrieval systems use your content as context input?

---

### MCP Server Card Tab

Configures `/.well-known/mcp/server-card.json`.

- **Server Name** — the display name for this site's MCP presence
- **Version** — a version string for your server card
- **Description** — a short description of the site for agents
- **Transport Endpoint URL** — the URL of your MCP server, if you have one running. Leave blank if you don't — the card is still valid
- **Capabilities** — a repeater where you can declare MCP capability keys (e.g. `tools`, `resources`, `prompts`)

---

### OAuth / OIDC Tab

Only relevant if your site runs an OAuth or OpenID Connect server. Must be enabled in the Features tab first.

- **Protocol** — choose between OpenID Connect (serves `/.well-known/openid-configuration`) or OAuth 2.0 (serves `/.well-known/oauth-authorization-server`)
- **Issuer** — your canonical issuer URL
- **Authorization Endpoint** — your `/oauth/authorize` URL
- **Token Endpoint** — your `/oauth/token` URL
- **JWKS URI** — your JSON Web Key Set URL
- **Grant Types** — check all that apply: `authorization_code`, `client_credentials`, `refresh_token`, `implicit`, `password`, `device_code`
- **Response Types** — check all that apply: `code`, `token`, `id_token`, and combinations
- **Token Auth Methods** — check all that apply: `client_secret_basic`, `client_secret_post`, etc.
- **Scopes** — a repeater to list each supported scope (e.g. `openid`, `profile`, `email`)

---

### OAuth Resource Tab

Only relevant alongside the OAuth/OIDC feature. Must be enabled in the Features tab first.

- **Resource Identifier** — the canonical URL that identifies this protected resource
- **Authorization Servers** — a repeater listing the issuer URLs of OAuth servers that can issue tokens for this resource
- **Bearer Methods** — check how you accept bearer tokens: `header`, `body`, and/or `query`
- **Scopes** — a repeater listing the scopes this resource accepts

---

### WebMCP Tab

Toggle which built-in browser tools are exposed to agents. All three require the WebMCP master toggle to be on in the Features tab.

- **Blog Search** — lets agents search your blog
- **Portfolio Navigation** — lets agents navigate to your portfolio page
- **Contact Navigation** — lets agents navigate to your contact page

---

## Screenshots

1. ![Settings — Agent Ready admin menu with submenu tabs](screenshots/screenshot-1.png)
   *The Agent Ready admin menu with all feature tabs*

---

## Frequently Asked Questions

**Do I need to configure everything before the plugin does anything?**
No. The plugin works out of the box with sensible defaults. The `/.well-known/` endpoints are live immediately after activation, Link headers are sent on every response, and the agent skills index auto-populates from your blog. You only need to visit the settings if you want to customise what's published or enable the OAuth features.

**Will this slow down my site?**
No. The `/.well-known/` endpoints only execute when an agent specifically requests them. The Link headers add a negligible number of bytes to each response. The WebMCP script is a few lines of JavaScript that only runs if `navigator.modelContext` exists in the browser — which it currently doesn't in standard browsers, so it has no runtime cost for regular visitors.

**What is the `/.well-known/` directory?**
It's a reserved URL path (defined by [RFC 8615](https://www.rfc-editor.org/rfc/rfc8615)) where internet standards place well-known resources — things like Let's Encrypt challenge files, Apple Pay domain verification, and increasingly, AI agent discovery documents. This plugin registers these paths through WordPress's rewrite system so no files need to physically exist on disk.

**Why do I need to save Permalinks after activation?**
WordPress stores its URL rewrite rules in the database and writes them to `.htaccess`. When the plugin registers its `/.well-known/` rules they don't take effect until the rewrite rules are regenerated, which happens when you save the Permalinks settings page. This is standard practice for any WordPress plugin that adds custom URL rules.

**I enabled OAuth/OIDC but I don't actually have an OAuth server — will anything break?**
The endpoint will serve whatever data you've entered in the settings, even if it's incomplete. Only enable this feature if you have a working OAuth or OIDC server — otherwise the metadata it publishes will be misleading to agents.

**Can I add custom agent skills or WebMCP tools from my theme or another plugin?**
Yes. Two filters are provided:

```php
// Add a custom agent skill
add_filter( 'kp_agent_skills', function ( array $skills ): array {
    $skills[] = [
        'name'        => 'my-skill',
        'type'        => 'api',
        'description' => 'Does something useful.',
        'url'         => 'https://yoursite.com/api/endpoint',
    ];
    return $skills;
} );

// Add a custom WebMCP tool
add_filter( 'kp_webmcp_tools', function ( array $tools ): array {
    // $tools is a PHP array that becomes the JS tools array
    return $tools;
} );
```

**Does this plugin work with WordPress multisite?**
It has not been tested on multisite. Each site in a network would need the plugin activated individually at the site level, and the `/.well-known/` paths would need to resolve correctly for each site's domain.

**Does this plugin require Composer?**
No — when installed from a release zip or cloned from GitHub, the `vendor/` directory containing all dependencies is included. You do not need to run `composer install`.

---

## Issues & Support

Found a bug or have a feature request? Please [open an issue](https://github.com/kpirnie/wppplugin-kp-agent-ready/issues) on GitHub. When reporting a bug, please include your WordPress version, PHP version, and a description of the problem along with any relevant error messages.

Pull requests are welcome.