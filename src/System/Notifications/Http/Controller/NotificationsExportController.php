<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Notifications\Http\Controller;

use DateTimeImmutable;
use Generator;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Export\CsvExport;
use Lemonade\Admin\Http\AdminResponseFactory;
use Lemonade\Admin\Notification\Models\NotificationModel;
use Lemonade\Admin\Notification\NotificationPresentation;
use Lemonade\Admin\System\Notifications\NotificationsExportSelection;
use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Http\Response\Responses;
use Lemonade\Framework\Localization\TranslatorInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Vytvari autorizovany CSV export vybranych management oznameni
 */
final class NotificationsExportController
{
    /**
     * Nastavuje authorization, management projekci a CSV presentation
     */
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly AdminResponseFactory $adminResponses,
        private readonly NotificationModel $notifications,
        private readonly NotificationPresentation $presentation,
        private readonly CsvExport $csv,
        private readonly TranslatorInterface $translator,
        private readonly Responses $responses,
    ) {}

    /**
     * Overuje vyber existujicich zaznamu a odesila jejich proudovany CSV export
     */
    public function export(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->authorization->hasPermission('system.notifications.view')) {
            return $this->adminResponses->authorizationDenied($request);
        }

        $selection = NotificationsExportSelection::fromInput((new RequestData($request))->postAll()['ids'] ?? null);
        if ($selection === null) {
            return $this->responses->text(
                $this->translator->get('notifications.validation.bulk_ids_invalid'),
                HttpStatusCode::UNPROCESSABLE_ENTITY->value,
            );
        }
        $ids = $selection->ids();
        if (count($this->notifications->lifecycleStates($ids)) !== count($ids)) {
            return $this->responses->text(
                $this->translator->get('notifications.validation.not_found'),
                HttpStatusCode::NOT_FOUND->value,
            );
        }

        return $this->responses->stream(
            producer: fn(): iterable => $this->csv->stream($this->header(), $this->rows($ids)),
            contentType: CsvExport::CONTENT_TYPE,
            headers: $this->csv->downloadHeaders($this->filename()),
        );
    }

    /**
     * Sklada lokalizovanou hlavicku management reportu
     *
     * @return list<string>
     */
    private function header(): array
    {
        return [
            'ID',
            $this->translator->get('notifications.fields.title'),
            $this->translator->get('notifications.fields.author'),
            $this->translator->get('notifications.fields.status'),
            $this->translator->get('notifications.fields.audience'),
            $this->translator->get('notifications.fields.created_at'),
            $this->translator->get('notifications.fields.updated_at'),
        ];
    }

    /**
     * Prevadi exportni projekci na CSV radky bez message obsahu
     *
     * @param list<int> $ids
     * @return Generator<int, array{0:int,1:string,2:string,3:string,4:string,5:string,6:string}>
     */
    private function rows(array $ids): Generator
    {
        foreach ($this->notifications->iterateExportRows($ids) as $notification) {
            yield [
                (int) $notification['id'],
                $notification['title'],
                $this->presentation->author($notification, $this->translator),
                $this->status($notification),
                $this->audience($notification['audience']),
                $notification['created_at'],
                $notification['updated_at'],
            ];
        }
    }

    /**
     * Lokalizuje effective lifecycle stav z active a soft-delete hodnot
     *
     * @param array{active:int,deleted_at:string|null,...} $notification
     */
    private function status(array $notification): string
    {
        $status = $notification['deleted_at'] !== null
            ? 'deleted'
            : ((int) $notification['active'] === 1 ? 'active' : 'inactive');

        return $this->translator->get('notifications.status.' . $status);
    }

    /**
     * Lokalizuje ulozene shrnuti publika pro export
     */
    private function audience(string $audience): string
    {
        [$type, $summary] = explode(':', $audience, 2) + ['', ''];

        return $this->translator->get(
            'notifications.audience_summary.' . ($type === 'roles' ? 'roles' : 'users'),
            ['audience' => $summary],
        );
    }

    /**
     * Vytvari casove rozlisitelny nazev CSV souboru
     */
    private function filename(): string
    {
        return 'notifications-' . (new DateTimeImmutable())->format('Y-m-d-His') . '.csv';
    }
}
