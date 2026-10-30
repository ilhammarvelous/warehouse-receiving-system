<?php

namespace App\Enums;

enum ErrorCode: string
{
    case Unauthenticated = 'UNAUTHENTICATED';
    case Forbidden = 'FORBIDDEN';
    case NotFound = 'NOT_FOUND';
    case ValidationError = 'VALIDATION_ERROR';
    case SkuAlreadyExists = 'SKU_ALREADY_EXISTS';
    case DuplicateProductInDocument = 'DUPLICATE_PRODUCT_IN_DOCUMENT';
    case QuantityExceedsRemaining = 'QUANTITY_EXCEEDS_REMAINING';
    case DocumentNotEditable = 'DOCUMENT_NOT_EDITABLE';
    case DocumentNotReceivable = 'DOCUMENT_NOT_RECEIVABLE';
    case DocumentNotFullyReceived = 'DOCUMENT_NOT_FULLY_RECEIVED';
    case DocumentHasReceipts = 'DOCUMENT_HAS_RECEIPTS';
    case InvalidStatusTransition = 'INVALID_STATUS_TRANSITION';
    case IdempotencyKeyReused = 'IDEMPOTENCY_KEY_REUSED';
    case InternalError = 'INTERNAL_ERROR';
}
