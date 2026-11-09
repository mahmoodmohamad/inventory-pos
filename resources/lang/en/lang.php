
<?php

return [
    'navigation' => 'Navigation',
    'call_us' => 'Call us',
    'shopping_cart' => 'Shopping cart',
    'cart' => 'Cart',
    'home' => 'Home',
    'products' => 'Products',
    'search' => 'Search',
    'add_to_cart' => 'Add to cart',
    'price' => 'Price',
    'quantity' => 'Quantity',
    'available' => 'Available',
    'out_of_stock' => 'Out of stock',
    'language' => 'Language',

    'nav' => [
        'features' => 'Features',
        'how' => 'How it works',
        'store' => 'Storefront',
        'faq' => 'FAQ',
    ],

    'login' => 'Log in',
    'register' => 'Start free',
    'dash' => 'Dashboard',

    'h1' => 'Every part, matched to the car it fits.',
    'sub' => 'A complete system for auto-parts shops: inventory with accurate weighted cost, invoicing and POS, customer and supplier accounts, and an online store that searches by make, model and year.',
    'cta2' => 'View demo store',
    'note' => 'No credit card. Ready in minutes.',

    'mock_title' => 'Pick your car',
    'mock' => ['Toyota', 'Corolla', '1.6 (2014–2018)'],
    'mock_count' => '3 parts fit Toyota Corolla 1.6',
    'cur' => 'EGP',

    'parts' => [
        ['Front brake pad set', 'Original', 'In stock', true, '850'],
        ['Oil filter', 'Original', 'In stock', true, '180'],
        ['Front shock absorber', 'Imported', 'Out of stock', false, '1,450'],
    ],

    'facts' => [
        ['Exact math', 'Decimal-precise money, no rounding drift'],
        ['Multi-shop', "Each shop's data is fully isolated"],
        ['3 roles', 'Owner, manager and cashier permissions'],
    ],

    'f_title' => 'Everything a parts shop needs, in one place',
    'f_sub' => 'From the opening balance to the last invoice, every movement is recorded and nothing is edited silently.',

    'features' => [
        ['Inventory & weighted cost', "Every purchase updates average and last cost. The movement ledger can't be edited or deleted."],
        ['Fast invoicing & POS', 'Scan a barcode or search by part number, retail or wholesale, line or invoice discounts, instant print.'],
        ['Customer accounts', 'Credit sales, collections and statements with the running balance, plus a WhatsApp payment reminder in one tap.'],
        ['Suppliers & purchases', 'Receive stock at unit cost, pay in full, part or on credit, and preview the new average before saving.'],
        ['Vehicle fitment', 'Link each part to make, model and variant. Customers see only what fits their car.'],
        ['Low-stock alerts', 'A reorder level per product and a dashboard showing what runs out first and stock value at cost.'],
    ],

    'h_title' => 'Up and running in three steps',

    'steps' => [
        ['Register your shop', 'Add your shop name and details. You become the owner.'],
        ['Add products & stock', 'Enter parts with prices and opening stock, then link them to cars.'],
        ['Sell & receive', 'Open an invoice, or share your store link with customers.'],
    ],

    's_title' => 'Your online store, without exposing your costs',
    's_sub' => 'The public store shows retail price and availability only. Wholesale price, cost and quantities are never exposed.',

    's_points' => [
        'Search by make, model and year, or by part number',
        "Order cart sent to your shop's WhatsApp",
        'A private link per shop that you can switch off anytime',
    ],

    'vis' => [
        ['Retail price', true],
        ['Availability', true],
        ['Wholesale price', false],
        ['Cost & margin', false],
        ['Quantities', false],
    ],

    'on' => 'Visible',
    'off' => 'Hidden',

    'faq_title' => 'Frequently asked questions',

    'faq' => [
        ['Is my data isolated from other shops?', 'Yes. Every shop runs in its own context, and cross-shop access is blocked in both the application and the database.'],
        ['Can I sell with zero stock?', "That's a shop setting. Allow selling into negative stock, or reject it."],
        ['Can a completed invoice be edited?', 'No. Invoices, purchases and payments are locked; corrections are made with a counter entry or a return.'],
        ['Does it support other currencies?', 'Yes: Egyptian pound, Saudi riyal, UAE dirham and US dollar.'],
    ],

    'final' => 'Ready to organise your shop?',
    'final_sub' => 'Register today and add your first product.',
    'switch' => 'العربية',

    'currency_egp' => 'EGP',

    'choose_car' => 'Choose Your Car',
    'choose_car_description' => 'Find compatible parts by selecting your car make, model, and year.',

    'make' => 'Make',
    'all_makes' => 'All Makes',
    'model' => 'Model',
    'all_models' => 'All Models',
    'select_make_first' => 'Select a make first',
    'variant_year' => 'Variant & Year',
    'all_variants' => 'All Variants',
    'select_model_first' => 'Select a model first',
    'present' => 'Present',

    'search_placeholder' => 'Search by part number or name, e.g. 04465',
    'search_aria_label' => 'Search by part number or product name',
    'show_products' => 'Show Products',

    'products_count' => ':count products',
    'fits_on_car' => 'Fits :car',
    'remove_car_filter' => 'Remove car filter',
    'remove_search_filter' => 'Remove search filter',
    'clear_all' => 'Clear All',

    'product_condition' => 'Product condition',
    'all' => 'All',

    'in_stock' => 'In Stock',
    'out_of_stock' => 'Currently Unavailable',

    'price_on_request' => 'Price on Request',
    'add_to_cart' => 'Add to Cart',
    'whatsapp' => 'WhatsApp',

    'whatsapp_product_message' => 'Hello, I would like to ask about: :product (:part_number)',

    'no_matching_products' => 'No Matching Products',
    'no_matching_products_hint' => 'Try removing the condition filter or searching by part number only.',
    'show_all_products' => 'Show All Products',

    'pagination' => 'Pagination',
    'previous' => 'Previous',
    'next' => 'Next',
    'page_of' => 'Page :current of :last',

    'breadcrumb' => 'Breadcrumb',
    'home' => 'Home',

    'brand' => 'Brand',
    'category' => 'Category',

    'product_identification' => 'Product Identification',
    'verify_part_numbers' => 'Verify the part numbers before placing your order.',
    'part_number' => 'Part Number',
    'oem_number' => 'OEM Number',
    'barcode' => 'Barcode',
    'no_identification_numbers' => 'No identification numbers are available for this product.',

    'product_details' => 'Product Details',
    'additional_product_information' => 'Additional information about this product.',

    'purchase_details' => 'Purchase Details',
    'available_to_order' => 'Available to Order',
    'currently_unavailable' => 'Currently Unavailable',
    'product_price' => 'Product Price',
    'price_on_request' => 'Price on Request',
    'add_to_cart' => 'Add to Cart',
    'ask_on_whatsapp' => 'Ask About This Product on WhatsApp',
    'check_compatibility_before_order' => 'Please verify the part number and vehicle compatibility before ordering.',
    'whatsapp_product_message' => 'Hello, I would like to ask about: :product',

    'compatible_vehicles' => 'Compatible Vehicles',
    'compatible_vehicles_description' => 'Vehicle models and variants compatible with this part.',
    'fitments_count' => ':count compatible applications',
    'compatible' => 'Compatible',
    'vehicle_compatibility' => 'Vehicle Compatibility',
    'no_fitment_data' => 'Vehicle compatibility information has not been added yet. Contact us to confirm whether this part fits your vehicle.',
    'ask_about_compatibility' => 'Ask About Compatibility',

    'back_to_products' => 'Back to All Products',
    'present' => 'Present',
'condition' => [
        'new'         => 'New',
        'used'        => 'Used',
        'original'    => 'Original',
        'aftermarket' => 'Aftermarket',
        'refurbished' => 'Refurbished',
    ],
];
