# Content reloads

When an editor saves a content element, a page or a news record, every open frontend tab
that shows this record reloads by itself. TYPO3's own cache tags decide which tabs: each
page knows the tags it rendered, each save knows the tags it flushed, and each tab compares
the two.

Saving is never slower, because the extension sends the change after the editor's response
has gone out. If sending fails, nothing happens and the save still works. A page without
tag data reloads on every change, so it never misses one.

## Turn on autoTagging

Turn on the `frontend.cache.autoTagging` feature toggle. Only new TYPO3 installations have
it on by default, so upgraded sites must set it:

```php
$GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['frontend.cache.autoTagging'] = true;
```

Without it, TYPO3 doesn't tag rendered content with `<table>_<uid>` tags, and content
reloads only happen for edits on the page you are looking at. File reloads don't depend on
it.

## Reloads for editors without a dev server

Content reloads also work where no Vite dev server runs, usually on a staging site. An
editor saves a record in the backend, and every preview tab of a logged-in backend user
that shows the record reloads.

Only the transport changes. Instead of the dev server's WebSocket, each tab asks a small
endpoint every few seconds whether something changed. Tag matching, reload modes, the
`typo3:live-reload` events and the Admin Panel module work the same. The Admin Panel's
Status tab shows which transport the page uses.

Install the package as a regular dependency, not with `--dev`, so it ships with your
release:

```bash
composer require wazum/typo3-live-reload
```

Then name the exact application context of that environment in `activeContexts`:

```
activeContexts = Development,Production/Staging
```

An entry matches itself and its subcontexts. The extension ignores a plain `Production`
entry, so a staging configuration that ends up on a real production system (context
`Production`) turns nothing on. The Development context keeps the Vite transport, and every
other allowed context polls. There is no transport setting.

Outside the Development context, the extension needs a valid backend user session. You
can't turn this off.

- Without a backend session, the extension adds nothing to the page: no configuration, no
  tag data, no script. Visitors get the same page they would get without the extension.
- Without a backend session, the poll endpoint `/__live-reload/poll` answers with a plain
  404. In contexts that aren't allowed at all, the extension doesn't handle the path, so it
  behaves like any other unknown URL on your site.

`pollInterval` sets how often each tab asks for changes, so a reload arrives within that
many milliseconds after a save. `retention` sets how long the extension keeps a change for
polling tabs. A tab that was hidden for longer reloads once to catch up. The defaults work
for normal editing.

## Tags from other extensions

Some extensions flush extra cache tags directly through the `CacheManager`. The
DataHandler's tag list doesn't include those. Add them back with a
`ModifyBroadcastTagsEvent` listener, for example for
[georgringer/news](https://extensions.typo3.org/extension/news):

```php
use TYPO3\CMS\Core\Attribute\AsEventListener;
use Wazum\LiveReload\Event\ModifyBroadcastTagsEvent;

#[AsEventListener(identifier: 'news/broadcast-tags')]
final class NewsBroadcastTagsListener
{
    public function __invoke(ModifyBroadcastTagsEvent $event): void
    {
        if ($event->getTable() !== 'tx_news_domain_model_news') {
            return;
        }

        $event->addTags('tx_news_uid_' . $event->getUid(), 'tx_news_pid_' . $event->getUidPage());
    }
}
```

The event has `getTable()`, `getUid()`, `getUidPage()`, `getTags()` and
`addTags(string ...$tags)`.

A tag only reloads tabs whose rendered page also carries it. The news extension adds tags
like `tx_news_uid_*` to the pages that show the record while it renders them. A tag that
only the broadcast has never matches anything.

## Limits

- The extension only sends changes that go through TYPO3's DataHandler. It doesn't see
  direct database writes.
- Tags that extensions flush outside the DataHandler's list need the
  [listener above](#tags-from-other-extensions).
- Content from an external index such as Solr updates when you reindex, not when you save.
