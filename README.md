# Topdata Topseller Export SW6

![Plugin Icon](src/Resources/config/plugin.png)

## Installation

1. Download the plugin
2. Upload to your Shopware 6 installation
3. Install and activate the plugin

## Requirements

- Shopware 6.7.*

## Features

This plugin provides functionality to export best-selling product data (topsellers) to a CSV file.
It supports both command-line interface (CLI) and an Admin API endpoint for manual exports from the Shopware 6 administration.

### Exported Data Columns

- `articleNumber`: The product's article number.
- `productName`: The localized name of the product.
- `salesCount`: The aggregated quantity sold for the product within the specified date range.

## Usage

### 1. Command Line Interface (CLI) Export

The plugin provides a CLI command to export topseller data, ideal for scheduled tasks (e.g., cron jobs).

**Command:**
`bin/console topdata:topseller:export [options]`

**Options:**

- `--date-range-preset (-p)`: Predefined date range for the export.
    - **Available presets:** `TODAY`, `YESTERDAY`, `THIS_WEEK`, `PREVIOUS_WEEK`, `THIS_MONTH`, `PREVIOUS_MONTH`, `THIS_YEAR`, `PREVIOUS_YEAR`, `LAST_7_DAYS`, `LAST_30_DAYS`, `LAST_365_DAYS`.
    - *Example:* `--date-range-preset=LAST_30_DAYS`

- `--start-date (-s)`: Custom start date for the export (format: `YYYY-MM-DD`). Cannot be used with `--date-range-preset`.
    - *Example:* `--start-date=2023-01-01`

- `--end-date (-e)`: Custom end date for the export (format: `YYYY-MM-DD`). Cannot be used with `--date-range-preset`.
    - *Example:* `--end-date=2023-01-31`

- `--output-path (-o)`: Destination directory for the CSV file. The filename will be automatically generated (e.g., `topsellers_YYYYMMDD_to_YYYYMMDD_HHmmss.csv`). Defaults to the current working directory.
    - *Example:* `--output-path=/var/www/html/public/exports`

- `--language-code (-l)`: Language code (e.g., `en-GB`, `de-DE`) for retrieving localized product names. If not specified, the system's default language will be used.
    - *Example:* `--language-code=de-DE`

**Examples:**

*   **Export topsellers from the last 30 days to the current directory:**
    ```bash
    bin/console topdata:topseller:export -p LAST_30_DAYS
    ```

*   **Export topsellers for January 2023 to a specific directory, using German product names:**
    ```bash
    bin/console topdata:topseller:export -s 2023-01-01 -e 2023-01-31 -o /path/to/my/exports -l de-DE
    ```

*   **Export topsellers from yesterday, saving to a "protected" public folder:**
    ```bash
    bin/console topdata:topseller:export -p YESTERDAY -o public/exports/topsellers
    ```
    *(Note: Ensure `/public/exports/topsellers` is protected with basic auth or similar mechanisms for security.)*

### 2. Admin API Export (Manual Download)

The plugin exposes an Admin API endpoint that allows for manual topseller exports. This endpoint can be triggered from a custom Shopware 6 Admin module (requires separate frontend development) or directly from a browser/tool for testing.

**Endpoint:**
`GET /api/_action/topdata-topseller-export-sw6/export`

**Query Parameters:**

- `startDate` (required): Start date for the export (format: `YYYY-MM-DD`).
- `endDate` (required): End date for the export (format: `YYYY-MM-DD`).
- `languageCode` (optional): Language code (e.g., `en-GB`, `de-DE`) for product names. If omitted, the system's default language will be used.

**Example Request (from browser or tool, assuming authentication):**

```
GET /api/_action/topdata-topseller-export-sw6/export?startDate=2024-01-01&endDate=2024-01-31&languageCode=en-GB
```

This will trigger a download of a CSV file containing topsellers from January 2024 with English product names.

**Integrating with Admin UI:**
To fully leverage this API endpoint within the Shopware 6 administration, a custom Vue.js-based Admin module would typically be developed. This module would provide a user-friendly interface for selecting date ranges and initiating the download. This frontend development is outside the scope of this plugin's backend implementation but can easily consume the provided API endpoint.

### 3. General Product Export (YAML Configured)

The plugin also includes a robust, YAML-configured CLI command to export all your products, resolving common CSV escaping issues (newlines, commas) associated with Twig-based exports.

**Command:**
`bin/console topdata:product:export [options]`

**Options:**
- `--config (-c)`: Path to a custom YAML configuration file. Defaults to the plugin's internal `product_export.yaml`.
- `--output-path (-o)`: Destination directory for the CSV file.
- `--language-code (-l)`: Language for translated product fields.

**Defining Columns via YAML:**
Create a `.yaml` file to define the columns you want to export. It supports dot-notation for nested fields (e.g., `translated.name`, `manufacturer.translated.name`).

```yaml
columns:
  - header: "Product Number"
    field: "productNumber"
  - header: "Name"
    field: "translated.name"
  - header: "Stock"
    field: "stock"
```

**Examples:**

*   **Export using default columns to current directory:**
    ```bash
    bin/console topdata:product:export
    ```

*   **Export using custom YAML file and saving to public folder:**
    ```bash
    bin/console topdata:product:export --config custom_columns.yaml -o public/exports
    ```

*   **Export in a specific language:**
    ```bash
    bin/console topdata:product:export -l de-DE
    ```

## License

MIT
