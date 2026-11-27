# Admin identity

Admin supports local passwords and a host-configured OIDC provider. Both authentication methods resolve to a local `system_user`, which is the only identity used for roles, permission overrides, audit actors, editor locks, and other local relations.

## Local authentication

A local account requires a password hash. Successful authentication stores the local user ID in the session, and authorization is calculated from local roles and overrides. An inactive or soft-deleted user cannot sign in.

## OIDC authentication

The OIDC provider verifies the external identity. Admin validates the authorization response, PKCE flow, ID token, and host admission rules before resolving `(issuer, subject)` through `system_user_identity`.

On the first accepted sign-in, Admin creates a local shadow user and identity link in one transaction. The account has no local password or permission override. If the host configures an OIDC default role, Admin assigns it only while the user has no role assignment; otherwise an administrator assigns local authorization separately. Later sign-ins resolve the same local user by issuer and subject. Email is not an identity key and is never used for automatic account linking.

For an externally linked account, verified profile fields are managed by the provider and remain readonly in the Users editor. Local state, roles, permission overrides, and supported local fields remain under Admin control.

## Session and audit

The session stores the local user ID and the authentication source. OIDC sessions also retain the provider context required for provider logout. Audit always identifies the actor by the local `system_user.id`.
