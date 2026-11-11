<?php

declare(strict_types=1);

/**
 * This file is part of simplysmart/polygon.
 *
 * Copyright (c) 2026 Marcin Ulezalka <marcin.ulezalka@simplysmart.pl>
 *
 * Licensed under the Simplysmart Restricted Source License 1.0.
 * See the LICENSE file distributed with this source code.
 */

/*
 * Teksty faktur i integracji z systemem fakturowym (Invoicing\*, docs/baza-danych.md §21).
 */
return [
    'token_required' => 'Enter the API token of the Fakturownia account.',
    'subdomain_invalid' => 'Enter the Fakturownia account name (subdomain) — lowercase letters, digits and hyphens.',
    'department_invalid' => 'The Fakturownia department id consists of digits only.',
    'unauthorized' => 'Fakturownia rejected the API token. Check the token and the account name.',
    'connection_failed' => 'Could not connect to Fakturownia: :reason',
    'client_without_name' => 'client without a name',
    'client_duplicate' => 'a second Fakturownia client for contractor :contractor (duplicate)',
    'account_missing' => 'The organization has no active Fakturownia account.',
    'contractor_foreign' => 'The contractor does not belong to this organization.',
    'payment_due_before_issue' => 'The payment due date cannot be earlier than the issue date.',
    'currency_invalid' => 'Enter a three-letter currency code.',
    'items_required' => 'Add at least one item.',
    'item_name_required' => 'Enter the item name.',
    'item_quantity_invalid' => 'The quantity must be a number greater than zero.',
    'item_price_invalid' => 'Enter the net price as a number.',
    'item_tax_invalid' => 'Enter a VAT rate from 0 to 100.',
    'send_failed' => 'The invoice could not be sent to Fakturownia.',
    'invoice_without_id' => 'The invoice has no identifier in Fakturownia.',
    'invoice_kind_unsupported' => 'Invoices of kind “:kind” are not downloaded (only VAT and pro forma invoices).',
    'invoice_without_date' => 'The invoice has no issue date.',
    'invoice_tax_unknown' => 'Item “:item” has an unsupported VAT rate “:tax”.',
    'invoice_client_missing' => 'The invoice client no longer exists in Fakturownia.',
    'invoice_buyer_missing' => 'The invoice buyer could not be determined.',
];
