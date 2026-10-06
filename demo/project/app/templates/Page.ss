<!DOCTYPE html>
<html lang="$ContentLocale">
<head>
    <% base_tag %>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>$Title – Supertext Silverstripe Demo</title>
    $MetaTags(false)
    <style>
        body { font: 17px/1.6 system-ui, sans-serif; margin: 0; color: #1f2933; background: #f7f8fa; }
        header, main, footer { max-width: 720px; margin: 0 auto; padding: 16px 20px; }
        header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
        header a { color: inherit; text-decoration: none; }
        .brand { font-weight: 600; }
        nav ul, .languages { list-style: none; display: flex; gap: 12px; margin: 0; padding: 0; }
        nav a, .languages a { color: #52606d; }
        nav .current a, .languages .current a { color: #e2001a; font-weight: 600; }
        article { background: #fff; border-radius: 8px; padding: 24px 28px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .element h2 { margin-top: 1.4em; }
        footer { color: #7b8794; font-size: 14px; }
    </style>
</head>
<body>
<header>
    <a class="brand" href="$BaseHref">Supertext Silverstripe Demo</a>
    <nav><ul><% loop $Menu(1) %><li class="$LinkingMode"><a href="$Link">$MenuTitle</a></li><% end_loop %></ul></nav>
    <ul class="languages">
        <% loop $Locales %><li class="$LinkingMode"><a href="$Link" hreflang="$HrefLang">$URLSegment.UpperCase</a></li><% end_loop %>
    </ul>
</header>
<main>
    <article>
        <h1>$Title</h1>
        $Content
        $ElementalArea
    </article>
</main>
<footer>Demo of <a href="https://github.com/Supertext/Silverstripe-Supertext-Translation">Supertext Translation for Silverstripe</a>.</footer>
</body>
</html>
