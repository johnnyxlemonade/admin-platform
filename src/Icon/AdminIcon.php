<?php

declare(strict_types=1);

namespace Lemonade\Admin\Icon;

/**
 * Definuje ikony pouzivane v administraci
 */
enum AdminIcon: string
{
    case Activity = 'activity';
    case ArrowClockwise = 'arrow-clockwise';
    case ArrowCounterclockwise = 'arrow-counterclockwise';
    case ArrowDown = 'arrow-down';
    case ArrowLeft = 'arrow-left';
    case ArrowUp = 'arrow-up';
    case ArrowsMove = 'arrows-move';
    case Bell = 'bell';
    case BoxArrowRight = 'box-arrow-right';
    case BoxArrowUpRight = 'box-arrow-up-right';
    case Boxes = 'boxes';
    case CheckCircle = 'check-circle';
    case CheckLg = 'check-lg';
    case Check2 = 'check2';
    case Check2All = 'check2-all';
    case ChevronBarLeft = 'chevron-bar-left';
    case ChevronDown = 'chevron-down';
    case ChevronUp = 'chevron-up';
    case Circle = 'circle';
    case ClockHistory = 'clock-history';
    case Display = 'display';
    case Export = 'download';
    case Envelope = 'envelope';
    case ExclamationCircle = 'exclamation-circle';
    case ExclamationTriangle = 'exclamation-triangle';
    case Eye = 'eye';
    case Floppy = 'floppy';
    case Funnel = 'funnel';
    case Gear = 'gear';
    case Grid1x2 = 'grid-1x2';
    case GripVertical = 'grip-vertical';
    case HouseDoor = 'house-door';
    case Inbox = 'inbox';
    case InfoCircle = 'info-circle';
    case JournalText = 'journal-text';
    case Key = 'key';
    case Lock = 'lock';
    case MoonStars = 'moon-stars';
    case PauseCircle = 'pause-circle';
    case PencilSquare = 'pencil-square';
    case People = 'people';
    case Person = 'person';
    case PersonAdd = 'person-add';
    case PersonGear = 'person-gear';
    case PlusLg = 'plus-lg';
    case QuestionCircle = 'question-circle';
    case Search = 'search';
    case ShieldCheck = 'shield-check';
    case ShieldLock = 'shield-lock';
    case Sliders = 'sliders';
    case Star = 'star';
    case Sun = 'sun';
    case ThreeDots = 'three-dots';
    case Trash3 = 'trash3';
    case Upload = 'upload';
    case XCircle = 'x-circle';
    case XLg = 'x-lg';

    /**
     * Vrati Bootstrap CSS tridy pro vykresleni ikony
     */
    public function cssClass(): string
    {
        return 'bi bi-' . $this->value;
    }
}
