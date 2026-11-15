# Durable proof photos

Production uses Cloudinary authenticated images. Store `PROOF_STORAGE=cloudinary`,
`CLOUDINARY_CLOUD_NAME`, `CLOUDINARY_API_KEY`, and `CLOUDINARY_API_SECRET` in the
existing Render service environment. Never commit real credentials. Local
development defaults to local files and may use `PROOF_STORAGE=local` explicitly.

Photo paths already saved in parcels and `[IMG]` history entries stay unchanged.
When a local file is absent, its existing URL is served by a parcel-authorized
handler, which fetches the authenticated original over verified HTTPS. The
Cloudinary secret and signed delivery URL stay on the server. Customer tracking,
driver history and printed receipts keep using their existing image tags.

New pickup/transit/delivery uploads must succeed before the database records are
committed. A failed database save rolls back status and removes only the new
asset from that request. Storage outages, invalid credentials and exhausted
provider quota produce retry feedback; production never silently saves to an
ephemeral local directory. Receiver signatures remain in the existing database.

After securely loading environment variables into a trusted terminal:

```
php tools/proof_storage.php check
php tools/proof_storage.php dry-run /path/to/DropIT-backup
php tools/proof_storage.php migrate /path/to/DropIT-backup
```

`check` uploads a synthetic one-pixel image, removes its local source, verifies a
fresh client can retrieve the same bytes, and removes that disposable test asset.
`dry-run` lists recoverable files without any remote request. `migrate` uploads
available proof/transit files and verifies their bytes; it leaves local originals
intact and skips identical existing cloud copies. It does not edit the database,
overwrite different assets, reconstruct missing photos, or change delivery status.

To verify durability after deployment, open a migrated proof in the normal app,
redeploy, sign in again if necessary, and confirm the same image still loads.
The free storage plan has usage limits; it does not remove Render Free cold starts.
