# be.ctrl.banking

- [Installation](#installation)
- [Configuration](#configuration)

## Installation

- You can directly clone to your CiviCRM extension directory using<br>
```$ git clone https://github.com/kewljuice/be.ctrl.banker.git```

- You can also download a zip file, and extract in your extension directory<br>
```$ git clone https://github.com/kewljuice/be.ctrl.banker/archive/master.zip```

- Configure CiviCRM Extensions Directory which can be done from<br>
```"Administer -> System Settings -> Directories".```

- Configure Extension Resource URL which can be done from<br>
```"Administer -> System Settings -> Resource URLs".```

- The next step is enabling the extension which can be done from<br> 
```"Administer -> System Settings -> Manage CiviCRM Extensions".```

## Configuration

### 1. Insert Bank account (execute commands at once)

``` 
# Select IBAN option value
SELECT @iban := id FROM civicrm_option_value WHERE name='IBAN'; 

# Insert bank account (Change parameters as desired)
INSERT INTO civicrm_bank_account (`description`, `created_date`, `modified_date`, `data_raw`, `data_parsed`, `contact_id`)
VALUES ('KBC', NOW(), NOW(), '{}', '{"name": "KBC Bank", "country": "BE", "BIC": "KREDBEBB"}', 1);
SET @kto := LAST_INSERT_ID();

# Insert bank account to link table  (Change reference as desired)
INSERT INTO civicrm_bank_account_reference (`reference`, `reference_type_id`, `ba_id`) 
VALUES ("BE68539007547034", @iban, @kto);
```

### 2. Create Importer Plugin via Interface

Configuration for Importer(s) can be done from ```"Banking -> Configuration Manager".``` 

Under Import Plugins press 'Add a new one'

| Parameter      | Value                          	            |
|----------------|----------------------------------------------|
| Plugin Name    | Import [Enter Name]                          |           
| Plugin Class   | Import plugin                                |
| Implementation | Configurable CSV Importer                    |  
| Description    | Import bank transfers from [Enter Name].     |

Configuration

```
{
  "delimiter": ";",
  "encoding": "UTF8",
  "header": 1,
  "title": "KBC {starting_date}--{ending_date} [{md5}]",
  "defaults": {},
  "rules": [
    {
      "comment": "company account",
      "field_type": "evaluated",
      "from": "Rekeningnummer",
      "to": "_IBAN",
      "type": "set"
    },
    {
      "comment": "Rubriek",
      "from": "Afschriftnummer",
      "to": "product",
      "type": "set"
    },
    {
      "comment": "extract currency*",
      "field_type": "required",
      "from": "EUR",
      "to": "currency",
      "type": "set"
    },
    {
      "comment": "extract currency*",
      "field_type": "required",
      "from": "Munt",
      "to": "currency",
      "type": "set"
    },
    {
      "field_type": "required",
      "from": "Datum",
      "to": "booking_date",
      "type": "strtotime:d/m/Y"
    },
    {
      "field_type": "required",
      "from": "Datum",
      "to": "value_date",
      "type": "strtotime:d/m/Y"
    },
    {
      "comment": "Description",
      "field_type": "evaluated",
      "from": "Omschrijving",
      "to": "purpose",
      "type": "set"
    },
    {
      "field_type": "required",
      "from": "Bedrag",
      "to": "amount",
      "type": "amount"
    }
  ]
}
```

### 3. Create OGM Analyser

Configuration for Analyzers can be done from ```"Banking -> Configuration Manager".``` 

Under Analyser / Matcher Plugins press 'Add a new one'

| Parameter      | Value                          	            |
|----------------|----------------------------------------------|
| Plugin Name    | OGM Analyser                                 |           
| Plugin Class   | Match plugin                                 |
| Implementation | RegEx Analyser                               |  
| Description    | Analyses OGM codes from purpose.             |


Configuration

```
{
  "rules": [
    {
      "comment": "OGM",
      "fields": [
        "purpose"
      ],
      "pattern": "#(?P<OGM>(?P<OGM_1>[0-9]{3})[/](?P<OGM_2>[0-9]{4})[/](?P<OGM_3>[0-9]{3})(?P<OGM_4>[0-9]{2}))#",
      "actions": [
        {
          "action": "copy",
          "from": "OGM",
          "to": "ogm"
        },
        {
          "action": "copy",
          "from": "OGM_1",
          "to": "ogm_number"
        },
        {
          "action": "copy_append",
          "from": "OGM_2",
          "to": "ogm_number"
        },
        {
          "action": "copy_append",
          "from": "OGM_3",
          "to": "ogm_number"
        },
        {
          "action": "copy",
          "from": "OGM_4",
          "to": "ogm_checksum"
        },
        {
          "comment": "Validate Code",
          "action": "calculate",
          "from": "(((int) \"{ogm_number}\") % 97) == (((int) \"{ogm_checksum}\") % 97)",
          "to": "ogm_is_valid"
        }
      ]
    }
  ]
}
```

### 4. Create Name Analyser

Configuration for Analyzers can be done from ```"Banking -> Configuration Manager".``` 

Under Analyser / Matcher Plugins press 'Add a new one'

| Parameter      | Value                          	            |
|----------------|----------------------------------------------|
| Plugin Name    | Name Analyser                                |           
| Plugin Class   | Match plugin                                 |
| Implementation | RegEx Analyser                               |  
| Description    | Analyses name from purpose.                  |


Configuration

```
{
  "rules": [
    {
      "comment": "Name analyser",
      "fields": [
        "purpose"
      ],
      "pattern": "#(?P<START>(.*?): [\\w-+]{8} (?P<NAME>(?P<NAME1>[\\w-+]*)[ ](?P<NAME2>[\\w-+]*)[ ](?P<NAME3>[\\w-+]*)))#",
      "actions": [
        {
          "action": "copy",
          "from": "NAME",
          "to": "name"
        },
        {
          "action": "copy",
          "from": "NAME1",
          "to": "name1"
        },
        {
          "action": "copy",
          "from": "NAME2",
          "to": "name2"
        },
        {
          "action": "copy",
          "from": "NAME3",
          "to": "name3"
        }
      ]
    }
  ]
}
```

### 5. Create Default Matcher

Configuration for Matcher(s) can be done from ```"Banking -> Configuration Manager".``` 

Under Analyser / Matcher Plugins press 'Add a new one'

| Parameter      | Value                          	            |
|----------------|----------------------------------------------|
| Plugin Name    | Default Matcher                              |           
| Plugin Class   | Match plugin                                 |
| Implementation | Default Options Matcher                      |  
| Description    | Provides some default processing options.    |


Configuration

```
{
  "generate": 1,
  "auto_exec": false,
  "manual_enabled": false,
  "manual_probability": "50%",
  "manual_show_always": true,
  "manual_title": "Manually processed",
  "manual_message": "Select this <strong>after</strong> you have manually processed this transaction.",
  "manual_contribution": "Please enter the resulting contribution ID here (if applicable):",
  "manual_default_source": "Offline",
  "manual_default_financial_type_id": 1,
  "ignore_enabled": true,
  "ignore_show_always": true,
  "ignore_probability": "0.1",
  "ignore_title": "Does not belong to CiviCRM",
  "ignore_message": "This payment should not be reconciled with contributions in CiviCRM.",
  "createnew_value_propagation": {
    "btx.source": "contribution.source",
    "btx.financial_type_id": "contribution.financial_type_id",
    "btx.campaign_id": "contribution.campaign_id",
    "btx.payment_instrument_id": "contribution.payment_instrument_id"
  },
  "value_propagation": {},
  "lookup_contact_by_name": {
    "soft_cap_probability": 0.8,
    "soft_cap_min": 10,
    "hard_cap_probability": 0.4
  }
}
```

### 5. Create Name Matcher

Configuration for Matcher(s) can be done from ```"Banking -> Configuration Manager".``` 

Under Analyser / Matcher Plugins press 'Add a new one'

| Parameter      | Value                          	            |
|----------------|----------------------------------------------|
| Plugin Name    | Name Matcher                                 |           
| Plugin Class   | Match plugin                                 |
| Implementation | Matcher Name                                 |  
| Description    | Matched to name.                             |


Configuration

```
{
  "auto_exec": false,
  "threshold": 0.9,
  "required_values": [
    "name1",
    "name2",
    "name3"
  ],
  "contribution_selector": [
    [
      "contribution_status_id",
      "2"
    ]
  ],
  "amount_penalty": "0.2",
  "value_propagation": {}
}
```

### 5. Create Multi Matcher

Configuration for Matcher(s) can be done from ```"Banking -> Configuration Manager".``` 

Under Analyser / Matcher Plugins press 'Add a new one'

| Parameter      | Value                          	            |
|----------------|----------------------------------------------|
| Plugin Name    | Multi Matcher                                |           
| Plugin Class   | Match plugin                                 |
| Implementation | Matcher Multi                                |  
| Description    | Matched to contribution set (OGM).           |


Configuration

```
{
  "auto_exec": false,
  "threshold": 0.9,
  "required_values": [
    "ogm"
  ],
  "contribution_selector": [
    [
      "contribution_status_id",
      "2"
    ],
    [
      "source",
      "{ogm}"
    ]
  ],
  "amount_penalty": "0.2",
  "value_propagation": {}
}
```
