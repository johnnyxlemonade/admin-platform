<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard\Models;

use Lemonade\Admin\Dashboard\DashboardWidgetSize;
use Lemonade\Framework\Database\Model;

/**
 * Zprostredkuje ulozene preference widgetu
 */
final class DashboardWidgetPreferenceModel extends Model
{
    protected string $table = 'admin_dashboard_widget_preference';
    protected bool $useTimestamps = true;
    protected bool $useSoftDeletes = true;
    protected array $allowedFields = ['user_id', 'owner_key', 'widget_code', 'personal_pinned', 'position', 'size'];

    /**
     * Vrati ulozenou preferenci widgetu pro vlastnika
     *
     * @return array<string, mixed>|null
     */
    public function findByOwnerKey(string $ownerKey, string $widgetCode): array|null
    {
        return $this->withDeleted()->first(['owner_key' => $ownerKey, 'widget_code' => $widgetCode]);
    }

    /**
     * Vrati aktivni preference widgetu serazene pro rozlozeni
     *
     * @return list<array<string, mixed>>
     */
    public function activeForOwnerKey(string $ownerKey): array
    {
        return $this->query()->where('owner_key', $ownerKey)->orderBy('position')->orderBy('widget_code')->getArray();
    }

    /**
     * Obnovi nebo vytvori osobni pripnuti widgetu
     */
    public function restoreOrCreatePersonalPin(string $ownerKey, int|null $userId, string $widgetCode, int $position, DashboardWidgetSize $fallbackSize): int
    {
        $existing = $this->findByOwnerKey($ownerKey, $widgetCode);
        if ($existing !== null) {
            $id = (int) $existing['id'];
            if ($existing['deleted_at'] !== null && !$this->restore($id)) {
                throw new \RuntimeException('Dashboard widget preference could not be restored.');
            }
            $this->update($id, ['personal_pinned' => 1]);

            return $id;
        }

        return (int) $this->insert([
            'owner_key' => $ownerKey,
            'user_id' => $userId,
            'widget_code' => $widgetCode,
            'personal_pinned' => 1,
            'position' => $position,
            'size' => $fallbackSize->value,
        ]);
    }

    /**
     * Obnovi nebo vytvori preferenci rozlozeni widgetu
     */
    public function restoreOrCreateLayoutPreference(string $ownerKey, int|null $userId, string $widgetCode, int $position, DashboardWidgetSize $size): int
    {
        $existing = $this->findByOwnerKey($ownerKey, $widgetCode);
        if ($existing !== null) {
            $id = (int) $existing['id'];
            if ($existing['deleted_at'] !== null && !$this->restore($id)) {
                throw new \RuntimeException('Dashboard widget preference could not be restored.');
            }
            $this->update($id, [
                'personal_pinned' => $existing['deleted_at'] === null ? (int) $existing['personal_pinned'] : 0,
                'position' => $position,
                'size' => $size->value,
            ]);

            return $id;
        }

        return (int) $this->insert([
            'owner_key' => $ownerKey,
            'user_id' => $userId,
            'widget_code' => $widgetCode,
            'personal_pinned' => 0,
            'position' => $position,
            'size' => $size->value,
        ]);
    }
}
