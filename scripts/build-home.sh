#!/bin/bash
# build-home.sh — 홈 섹션 overlay·자산 생성기와 대조기(0.4.0).
#
#   scripts/build-home.sh          생성물을 다시 쓴다
#   scripts/build-home.sh --check  파일을 쓰지 않고 생성물이 원본과 같은지 + 구조 검사만 한다
#                                  (모두 통과 0, 하나라도 실패 1)
#
# 원본 → 생성물
#   resources/home/overlay.base.json + resources/home/registry.json + resources/home/widgets/<id>.json
#       → resources/extensions/home.json   (칸 노드 children "__WIDGETS__" 자리에 등록부 순서로 조각을 넣는다)
#   resources/css/home.css → dist/css/plugin.css
#   resources/js/home.js   → dist/js/plugin.iife.js
#
# 구조 검사(--check 와 생성 모두 수행)
#   1 최대 깊이 < 10 (주입 components 원소 = 깊이 0, 코어 ValidLayoutExtensionStructure::MAX_DEPTH = 10)
#   2 노드 id 중복 0
#   3 조각 종류 = registry.json = PHP 위젯 id (src/Home/Widgets/*Widget.php 의 id() 반환값)
#   4 사용 컴포넌트 ⊂ 허용 목록(두 템플릿 공통 basic + HtmlContent)
#   5 고정 아이콘 이름·PHP 기본 아이콘 ⊂ resources/home/icons.json
#
# 도구: bash, jq, cmp, grep, sed. 결과는 새 임시 파일에 먼저 쓰고 교체한다.
set -euo pipefail
export LC_ALL=C

root=$(cd "$(dirname "$0")/.." && pwd)
mode=build
case "${1:-}" in
  '') ;;
  --check) mode=check ;;
  *) echo "usage: $0 [--check]" >&2; exit 2 ;;
esac

MAX_DEPTH_EXCLUSIVE=10
ALLOWED='["Div","Span","A","H3","Ul","Li","Img","Icon","Button","P","HtmlContent"]'

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
status=0
fail() { echo "FAIL: $*"; status=1; }

# ---------- 생성 ----------
registry="$root/resources/home/registry.json"
jq -e 'type == "array" and length > 0 and all(.[]; type == "string")' "$registry" > /dev/null \
  || { echo "registry.json 형식 오류" >&2; exit 2; }

frags=()
while IFS= read -r id; do
  f="$root/resources/home/widgets/$id.json"
  [ -f "$f" ] || { echo "조각 없음: resources/home/widgets/$id.json" >&2; exit 2; }
  frags+=("$f")
done < <(jq -r '.[]' "$registry")

jq -s '.' "${frags[@]}" > "$work/frags.json"
jq --indent 4 --slurpfile frags "$work/frags.json" '
  del(._comment)
  | (.injections[0].components[0].children[0].children[0].children) |= (if . == "__WIDGETS__" then $frags[0] else error("placeholder 없음") end)
' "$root/resources/home/overlay.base.json" > "$work/home.json"

# 관리자 설정 화면(0.4.0 2묶음): resources/home/admin/* → resources/layouts/admin/plugin_settings.json
admin="$root/resources/home/admin"
FILL='def fill($k; $v): walk(if type == "array" then map(if . == $k then (if ($v|type) == "array" then $v[] else $v end) else . end) elif . == $k then $v else . end);'
stamp() { # 파일 [찾을 문자열 바꿀 문자열]... → 모든 문자열 값에서 바꾼다
  local f=$1; shift
  local prog='.' i=0 args=()
  while [ $# -gt 0 ]; do
    args+=(--arg "a$i" "$1" --arg "b$i" "$2")
    prog="$prog | walk(if type == \"string\" then gsub(\$a$i; \$b$i) elif type == \"object\" then with_entries(.key |= gsub(\$a$i; \$b$i)) else . end)"
    i=$((i + 1)); shift 2
  done
  jq "${args[@]}" "$prog" "$f"
}
jq -c '[.[] | {value: ., label: ("$t:g7-home-widgets.home.types." + .)}]' "$registry" > "$work/type_opts.json"
jq -c '[{value: "", label: "$t:g7-home-widgets.home.col.default_icon"}] + [.icons[] | {value: ., label: .}]' "$root/resources/home/icons.json" > "$work/icon_opts.json"
for t in layout s1 s2 s3 s4 s5; do stamp "$admin/tab.json" __TAB__ "$t"; done | jq -s '.' > "$work/tabs.json"
for s in 1 2 3 4 5; do stamp "$admin/section-row.json" __S__ "$s"; done | jq -s '.' > "$work/rows.json"
: > "$work/panels.ndjson"
for s in 1 2 3 4 5; do
  for c in 1 2; do
    stamp "$admin/col-panel.json" __K__ "s${s}c${c}" __S__ "$s" __C__ "$c" \
      | jq --slurpfile o "$work/type_opts.json" --slurpfile i "$work/icon_opts.json" "$FILL fill(\"__TYPE_OPTIONS__\"; \$o[0]) | fill(\"__ICON_OPTIONS__\"; \$i[0])"
  done | jq -s '.' > "$work/cols.json"
  stamp "$admin/section-panel.json" __S__ "$s" | jq --slurpfile c "$work/cols.json" "$FILL fill(\"__COLS__\"; \$c[0])" >> "$work/panels.ndjson"
done
jq -s '.' "$work/panels.ndjson" > "$work/panels.json"
jq '[.. | objects | select(.id == "board_filter_panel") | .children[0].children[]]' "$admin/board_filter.v030.json" > "$work/exclusion.json"
[ "$(jq 'length' "$work/exclusion.json")" -eq 4 ] || { echo "공통 제외 영역 노드 추출 실패" >&2; exit 2; }
# 관리자 화면 생성물은 한 줄(compact)로 쓴다 — 칸 10개 × 아이콘 선택지 141개라 들여쓰기하면 파일이 커진다.
jq -c --slurpfile tabs "$work/tabs.json" --slurpfile rows "$work/rows.json" --slurpfile ex "$work/exclusion.json" --slurpfile panels "$work/panels.json" \
  "$FILL del(._comment) | fill(\"__TABS__\"; \$tabs[0]) | fill(\"__SECTION_ROWS__\"; \$rows[0]) | fill(\"__EXCLUSION__\"; \$ex[0]) | fill(\"__SECTION_PANELS__\"; \$panels[0])" \
  "$admin/page.base.json" > "$work/plugin_settings.json"

cp "$root/resources/css/home.css" "$work/plugin.css"
cp "$root/resources/js/home.js" "$work/plugin.iife.js"

# ---------- 구조 검사 ----------
depth=$(jq 'def d: if (.children | type) == "array" and (.children | length) > 0 then 1 + ([.children[] | d] | max) else 0 end;
            [.injections[].components[] | d] | max' "$work/home.json")
echo "max depth: $depth (< $MAX_DEPTH_EXCLUSIVE)"
[ "$depth" -lt "$MAX_DEPTH_EXCLUSIVE" ] || fail "최대 깊이 $depth"

dups=$(jq '[.. | objects | select(has("type") and has("name")) | .id // empty] | group_by(.) | map(select(length > 1)) | length' "$work/home.json")
echo "duplicate node ids: $dups"
[ "$dups" -eq 0 ] || fail "노드 id 중복 $dups"

reg_ids=$(jq -r '.[]' "$registry" | sort | tr '\n' ' ')
frag_ids=$(jq -r '.if | capture("col\\.type === '"'"'(?<t>[a-z]+)'"'"'").t' "${frags[@]}" | sort | tr '\n' ' ')
php_ids=$(grep -h -A2 'public function id(): string' "$root"/src/Home/Widgets/*Widget.php | sed -n "s/.*return '\([a-z]*\)';.*/\1/p" | sort | tr '\n' ' ')
echo "types registry=[$reg_ids] fragments=[$frag_ids] php=[$php_ids]"
[ "$reg_ids" = "$frag_ids" ] && [ "$reg_ids" = "$php_ids" ] || fail "종류 목록 불일치"
for f in "${frags[@]}"; do
  id=$(basename "$f" .json)
  jq -e --arg id "$id" '.props.className == ("g7hw-w g7hw-w-" + $id)' "$f" > /dev/null || fail "$id 조각 클래스"
done

bad=$(jq --argjson ok "$ALLOWED" '[.. | objects | select(has("type") and has("name")) | select((.type != "basic" and .type != "composite") or ((.name as $n | $ok | index($n)) == null)) | .name] | unique' "$work/home.json")
echo "components outside allow-list: $bad"
[ "$bad" = "[]" ] || fail "허용 밖 컴포넌트"

icons="$root/resources/home/icons.json"
lit=$(jq -r '[.. | objects | select(.name == "Icon") | .props.name | select(test("\\{\\{") | not)] | unique | .[]' "$work/home.json")
php_icons=$(grep -h -A2 'public function defaultIcon(): string' "$root"/src/Home/Widgets/*Widget.php | sed -n "s/.*return '\([a-z0-9-]*\)';.*/\1/p")
missing=0
for name in $lit $php_icons; do
  jq -e --arg n "$name" '.icons | index($n) != null' "$icons" > /dev/null || { fail "아이콘 서브셋 밖: $name"; missing=$((missing + 1)); }
done
echo "icons checked: $(echo $lit $php_icons | wc -w) (outside subset: $missing)"

# 관리자 화면: 남은 자리표시 0, 노드 id 중복 0, 칸 패널 10개, 탭 6개
left=$(grep -c '__[A-Z_]*__' "$work/plugin_settings.json" || true)
adups=$(jq '[.. | objects | select(has("type") and has("name")) | .id // empty] | group_by(.) | map(select(length > 1)) | length' "$work/plugin_settings.json")
apanels=$(jq '[.. | objects | select(.props?["data-testid"]? // "" | test("^g7hw-admin-s[1-5]c[12]$"))] | length' "$work/plugin_settings.json")
echo "admin: placeholders left=$left duplicate ids=$adups col panels=$apanels tabs=$(jq 'length' "$work/tabs.json")"
[ "$left" -eq 0 ] && [ "$adups" -eq 0 ] && [ "$apanels" -eq 10 ] || fail "관리자 화면 생성 검사"

# ---------- 생성물 대조·쓰기 ----------
sync_one() { # 임시본 대상
  local src=$1 dst=$2 rel=${2#"$root"/}
  if [ -f "$dst" ] && cmp -s "$src" "$dst"; then
    echo "up to date: $rel"
    return
  fi
  if [ "$mode" = check ]; then
    fail "OUT OF DATE: $rel"
    return
  fi
  mkdir -p "$(dirname "$dst")"
  cp "$src" "$dst.tmp.$$"
  [ -f "$dst" ] && chmod --reference="$dst" "$dst.tmp.$$" 2>/dev/null || chmod 644 "$dst.tmp.$$"
  mv -f "$dst.tmp.$$" "$dst"
  echo "written: $rel"
}
sync_one "$work/home.json" "$root/resources/extensions/home.json"
sync_one "$work/plugin_settings.json" "$root/resources/layouts/admin/plugin_settings.json"
sync_one "$work/plugin.css" "$root/dist/css/plugin.css"
sync_one "$work/plugin.iife.js" "$root/dist/js/plugin.iife.js"

[ "$status" -eq 0 ] && echo "BUILD_HOME_OK" || echo "BUILD_HOME_FAIL"
exit "$status"
