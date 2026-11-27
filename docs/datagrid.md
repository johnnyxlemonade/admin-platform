# DataGrid

`DataGridProviderInterface` provides a `DataGridDefinition`, query execution, and the required permission. The definition declares columns, filters, supported sort keys, default view, and pagination. `DataGridController` serializes the result to JSON.

The query contract uses `sort=<key>&direction=<asc|desc>`. `DataGridQueryValidator` accepts only declared sort keys, filters, and directions. Browser state is stored per module; it does not change the index-page URL.

Providers return typed `DataGridRowDefinition` rows and cells. Query construction, ordering, and pagination remain the responsibility of the owning module model or service.

## Row actions

`DataGridRowActionDefinition` supports navigation, modal, and mutation actions. New actions must declare their `DataGridRowActionKind` explicitly. Placement and risk control presentation only; the action handler and service remain responsible for authorization, current state, and domain invariants.

Mutations use `ModuleActionController`. `ConfirmationDefinition` adds client confirmation but is not a security boundary. The `refresh` flag controls whether a successful mutation or modal save reloads the DataGrid.
