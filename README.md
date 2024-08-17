[![Unit Test](https://github.com/hidden-hint/ext-mass-convert/actions/workflows/test-unit.yml/badge.svg)](https://github.com/hidden-hint/ext-mass-convert/actions/workflows/test-unit.yml)

# Mass Lead Conversion Extension for EspoCRM

An extension that allows bulk lead conversion to arbitrary entity types. It adds a new action to the lead list view that allows you to select multiple leads and convert them to any entity type that is enabled in the system. Works also with custom entity types.

## Installation

1. Copy the contents of `src/files/custom/Espo/Modules/` to `custom/Espo/Modules/` of your EspoCRM installation.
2. Copy the contents of `src/files/client/custom/modules/` to `client/custom/modules/` of your EspoCRM installation.
3. Clear your EspoCRM cache and rebuild the application.

## Configuration

Add following to your `custom/Espo/Custom/Resources/metadata/entityDefs/Lead.json` file:

```json
{
  "massConvert": [
    "Contact",
    "Account",
    "Opportunity",
    "cYourCustomEntity"
  ]
}
```

Mass action will convert selected leads to the entity types listed in the `massConvert` array. You can add any entity type that is enabled in your system.

Additionally, you can specify fields map for each entity type. Add following to your `custom/Espo/Custom/Resources/metadata/entityDefs/Lead.json` file:

```json
{
  "convertFields": {
    "Contact": {
      "salutationName": "salutationName",
      "firstName": "firstName",
      "lastName": "lastName",
      "middleName": "middleName",
      "emailAddress": "emailAddress",
      "phone": "phone",
      "cYourCustomLeadField": "cYourCustomContactField"
    }
  }
}
```
