<?php

declare(strict_types=1);

namespace Lemonade\Admin\Http;

/**
 * Vymezuje chybove kody JSON odpovedi administrace
 */
enum AdminErrorCode: string
{
    case VALIDATION_FAILED = 'validation_failed';
    case PERMISSION_DENIED = 'permission_denied';
    case LOCAL_ACTOR_REQUIRED = 'local_actor_required';
    case NOT_FOUND = 'not_found';
    case LOCK_CONFLICT = 'lock_conflict';
    case UNSUPPORTED_ACTION = 'unsupported_action';
    case UNAUTHENTICATED = 'unauthenticated';
    case FORBIDDEN = 'forbidden';
    case MODULE_NOT_FOUND = 'module_not_found';
    case MODULE_NOT_AVAILABLE = 'module_not_available';
    case DATAGRID_NOT_SUPPORTED = 'datagrid_not_supported';
    case NOTIFICATION_ACTION_INVALID = 'notification_action_invalid';
    case DASHBOARD_WIDGET_CONTENT_FAILED = 'dashboard_widget_content_failed';
    case DASHBOARD_WIDGET_NOT_FOUND = 'dashboard_widget_not_found';
    case DASHBOARD_WIDGET_FORBIDDEN = 'dashboard_widget_forbidden';
    case DASHBOARD_WIDGET_NOT_PINNED = 'dashboard_widget_not_pinned';
    case DASHBOARD_WIDGET_NOT_AVAILABLE = 'dashboard_widget_not_available';
    case DASHBOARD_WIDGET_MANAGED = 'dashboard_widget_managed';
    case DASHBOARD_WIDGET_ORDER_INVALID = 'dashboard_widget_order_invalid';
    case DASHBOARD_WIDGET_ACTION_INVALID = 'dashboard_widget_action_invalid';
    case DASHBOARD_WIDGET_SIZE_INVALID = 'dashboard_widget_size_invalid';
    case UNSUPPORTED_LOCALE = 'unsupported_locale';
    case TRANSLATION_GROUP_NOT_FOUND = 'translation_group_not_found';
    case TRANSLATION_VERSION_NOT_FOUND = 'translation_version_not_found';
}
