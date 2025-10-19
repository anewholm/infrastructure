<div class="report-widget widget-olap">
    <h3><?= e(trans($this->property('title'))) ?></h3>

    <?php if (!isset($error)): ?>
        <div class="welcome-container">
            <div class="backend-content">
                <ul class="webapps">
                    <?php if (isset($webapps)) {
                        foreach ($webapps as $webapp) {
                            $title = $webapp['title'];
                            $url   = $webapp['url'];
                            $image = $webapp['image'];
                            $cubes = $webapp['cubes'];

                            print("<li>");
                            print('<img src="/modules/acorn/assets/images/cube.png"/>');
                            print("<a target='_blank' href='$url'>$title");
                            if ($image && false) print("<img src='$image'/>");
                            print('<ul class="cubes">');
                            foreach ($cubes as $name => $cube) {
                                print("<li>$name</li>");
                            }
                            print('</ul></a></li>');
                        }
                    } ?>
                </ul>
            </div>
        </div>
    <?php else: ?>
        <div class="callout callout-warning">
            <div class="content"><?= e($error) ?></div>
        </div>
    <?php endif ?>
</div>
