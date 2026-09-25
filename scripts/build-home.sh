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
sync_one "$work/plugin.css" "$root/dist/css/plugin.css"
sync_one "$work/plugin.iife.js" "$root/dist/js/plugin.iife.js"

[ "$status" -eq 0 ] && echo "BUILD_HOME_OK" || echo "BUILD_HOME_FAIL"
exit "$status"
