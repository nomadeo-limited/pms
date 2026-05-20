<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'NomadeoPMS API',
    description: 'Property Management System for adventure travel businesses (surf camps, yoga retreats, etc.)',
    contact: new OA\Contact(email: 'dev@nomadeo.com'),
    license: new OA\License(name: 'MIT')
)]
#[OA\Server(
    url: '/api/v1',
    description: 'NomadeoPMS API v1'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]
#[OA\Tag(name: 'Auth', description: 'Authentication endpoints')]
#[OA\Tag(name: 'Organizers', description: 'Organizer management (super admin only)')]
#[OA\Tag(name: 'Properties', description: 'Property management')]
#[OA\Tag(name: 'Inventory', description: 'Room types and bookable units')]
#[OA\Tag(name: 'Programs', description: 'Programs and add-ons')]
#[OA\Tag(name: 'Availability', description: 'Availability calendar and rules')]
#[OA\Tag(name: 'Pricing', description: 'Pricing rules and discounts')]
#[OA\Tag(name: 'Customers', description: 'Customer management')]
#[OA\Tag(name: 'Bookings', description: 'Booking management')]
#[OA\Tag(name: 'Payments', description: 'Payment tracking')]
#[OA\Tag(name: 'Staff', description: 'Staff management')]
#[OA\Tag(name: 'Reporting', description: 'Analytics and reports')]
#[OA\Tag(name: 'Integration', description: 'Nomadeo marketplace integration endpoints')]
class OpenApiInfo extends Controller {}
