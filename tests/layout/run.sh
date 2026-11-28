#!/bin/zsh
# PDF layout check: deploy the plugin to a TEST WordPress site, seed the fixture reports, render each one through
# the real export path, and check every page for layout problems. See README.md.
#
#   tests/layout/run.sh                 all fixtures (sample minimal long optimization optimization-minimal)
#   tests/layout/run.sh sample          just one
#
#   CRG_SSH_HOST   ssh host of the test site          (default: iaditya)
#   CRG_WP_PATH    WordPress root on that host        (default: site/public_html)
#   CRG_NO_DEPLOY  set to 1 to skip copying the plugin (test what's already on the site)
#
# Exit code = number of layout problems found (0 = all good). Output: tests/layout/out/<fixture>/
set -euo pipefail
exec 2> >(grep -v '^\\360' >&2)   # drop the ssh login banner some hosts print

HERE=${0:A:h}
PLUGIN=${HERE:h:h}
HOST=${CRG_SSH_HOST:-iaditya}
WP=${CRG_WP_PATH:-site/public_html}
OUT=$HERE/out
BIN=$HERE/.build
fixtures=("$@"); (( $#fixtures )) || fixtures=(sample minimal long optimization optimization-minimal)

# 1. Lint, and build the macOS checking tools (PDFKit) when their source changed.
for f in $PLUGIN/cleanup-report-generator.php $PLUGIN/includes/*.php $PLUGIN/templates/*.php $HERE/*.php $HERE/fixtures/*.php; do
  php -l "$f" >/dev/null || { echo "PHP syntax error in $f"; exit 100; }
done
[[ -f $PLUGIN/vendor/autoload.php ]] || { echo "vendor/ missing: run composer install first"; exit 100; }
mkdir -p $BIN
for t in pdf2png layoutcheck montage; do
  if [[ ! -x $BIN/$t || $HERE/tools/$t.swift -nt $BIN/$t ]]; then
    swiftc -O -o $BIN/$t $HERE/tools/$t.swift 2>/dev/null || { echo "could not build $t (needs Xcode command line tools)"; exit 100; }
  fi
done

# 2. Deploy the plugin exactly as it ships (files in .distignore left out) and upload the fixtures to a private temp dir.
if [[ ${CRG_NO_DEPLOY:-0} != 1 ]]; then
  echo "Deploying plugin to $HOST:$WP"
  rsync -az -e 'ssh -q' --delete --delete-excluded --exclude-from="$PLUGIN/.distignore" \
    "$PLUGIN/" "$HOST:$WP/wp-content/plugins/cleanup-report-generator/"
fi
REMOTE=$(ssh -q $HOST 'mktemp -d')
trap 'ssh -q $HOST "rm -rf $REMOTE"' EXIT
scp -q $HERE/render.php $HERE/fixtures/*.php $HERE/fixtures/*.png "$HOST:$REMOTE/"

# 3. Seed, render, fetch and check each fixture.
problems=0
for fx in $fixtures; do
  [[ -f $HERE/fixtures/$fx.php ]] || { echo "no fixture named $fx"; exit 100; }
  echo "\n== $fx"
  id=$(ssh -q $HOST "cd $WP && wp eval-file $REMOTE/$fx.php $REMOTE")
  ssh -q $HOST "cd $WP && wp eval-file $REMOTE/render.php $id $REMOTE/$fx.pdf" | sed "s/^/   report #$id  /"
  rm -rf $OUT/$fx && mkdir -p $OUT/$fx
  scp -q "$HOST:$REMOTE/$fx.pdf" "$HOST:$REMOTE/$fx.pdf.headings" $OUT/$fx/
  $BIN/pdf2png $OUT/$fx/$fx.pdf $OUT/$fx/page 1.0 >/dev/null
  $BIN/montage $OUT/$fx/overview.png $OUT/$fx/page-*(n) 2>/dev/null || true
  $BIN/layoutcheck $OUT/$fx/$fx.pdf $OUT/$fx/$fx.pdf.headings && n=0 || n=$?
  problems=$(( problems + n ))
done

echo
(( problems == 0 )) && echo "All fixtures passed." || echo "$problems layout problem(s). Pages: $OUT/<fixture>/page-N.png"
exit $(( problems > 99 ? 99 : problems ))
