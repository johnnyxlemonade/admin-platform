# AdminEditor

`AdminEditorBuilder` creates an immutable `AdminEditorDefinition` containing a form, fields, blocks, sections, tabs, optional sidebar content, and save controls. `AdminEditorRenderer` renders the definition with an `AdminEditorRenderContext` carrying current values, old input, validation errors, and create or edit mode.

Field definitions describe controls and layout only. Runtime values and errors belong to `AdminEditorRenderContext`. `AdminEditorFieldRenderer` dispatches explicitly by concrete field type; new field behavior requires a concrete immutable definition and a renderer branch.

`EditorDispatcher` accepts only declared fields, checks editor and field permissions, applies the provider validation schema, and delegates persistence to the registered editor provider. A provider may return field-specific transactional errors through `EditorValidationException`.

Full-page editors acquire an edit lock through `EditorLockManager`. Modal editors use the same editor definitions and lock contract through `EditorModalLoader`. Users additionally use record versions for optimistic locking.

Use existing field types and `CustomViewBlock` for module-specific presentation. Do not introduce a parallel editor abstraction for a single module.
