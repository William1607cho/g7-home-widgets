# g7-home-widgets

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](./LICENSE)

[그누보드7](https://sir.kr/) `sirsoft-board` 게시판을 쓰는 사이트의 **홈 화면 위젯 모음** 플러그인입니다.

- **홈 섹션**: 홈 화면에 섹션 5개(섹션마다 켜기/끄기, 1단·2단)를 그리고, 칸마다 최근글·인기글·
  뉴스 티커·갤러리·웹진 스타일·HTML 위젯을 고릅니다. 관리자 화면 **홈 화면 설정**에서 정합니다.
- 0.5.0 에서 0.4.x 까지 남아 있던 옛 위젯 API 와 공통 제외 설정을 뺐습니다. 업데이트 전에 아래
  "0.5.0 변경 안내"를 확인하세요.

`sirsoft-board`·템플릿 파일은 수정하지 않습니다. 홈 섹션은 레이아웃 확장(overlay)으로 홈 화면에
주입하고, 글 목록·열람 권한·비밀글 판정은 `sirsoft-board` 코어 서비스에 맡깁니다.

## 홈 섹션 (0.4.0)

- **구성**: 섹션 1~5 가 위에서부터 차례로 놓이고(템플릿 홈 내용보다 위), 2단 섹션은 왼쪽·오른쪽 칸이 1:1,
  좁은 화면에서는 세로로 쌓입니다. 꺼진 섹션은 그리지 않습니다.
- **기본값**(설정 저장 전): 1번 섹션 2단(최근글·인기글), 2번 섹션 1단(`notice` 게시판 뉴스 티커),
  3~5번 꺼짐 — 0.3.0 홈과 같은 모양입니다. 설정은 플러그인 설정 파일의 `home_layout` 키에 저장되며,
  키가 없으면 이 기본값을 씁니다(업데이트할 때 파일을 고쳐 쓰지 않습니다).
- **위젯 종류**
  - 최근글 · 인기글(이번주/이번달/1년 탭, 다시 요청하지 않고 전환) · 뉴스 티커(한 줄씩 넘김, 마우스·포커스
    시 멈춤, `prefers-reduced-motion` 이면 고정)
  - 갤러리: 썸네일 카드. 썸네일은 `sirsoft-board` 목록 썸네일(`PostResource`)이고, g7-image-delivery 0.3.0
    이상이 있으면 그 변환본(240, 고밀도 화면은 960)으로 보냅니다(아래 "이미지 전송 연동"). 없으면 대체 틀,
    비밀글은 자물쇠입니다.
  - 웹진 스타일: 썸네일 + 제목 + 요약 + 게시판 이름·분류 + 날짜 목록. **g7-webzine-addon 1.3.0 이상**이
    필요합니다(아래 "웹진 애드온 연동").
  - HTML: 관리자가 쓴 HTML. 저장할 때 코어 `HtmlSanitizer` 로 정제하고, 보여 줄 때 한 번 더 정제합니다
    (스크립트·이벤트 속성·`javascript:` 링크 제거).
- 최근글·인기글·웹진은 "게시판 이름 · 분류"를 보여 주고, 위키 게시판(g7-light-wiki `wiki_boards` 설정)의 글은
  분류를 빼고 게시판 이름만 보여 줍니다.
- **게시판 선택은 위젯마다** 따로 합니다. 보여 주는 게시판 = 보는 사람이 읽을 수 있는 게시판(코어 판정)에서
  위젯의 "포함 안 함" 목록을 뺀 것(최근글·인기글·갤러리·웹진) 또는 고른 게시판 1개(티커). 새로 만든 게시판은
  자동으로 포함됩니다. 게시판 선택을 저장하지 않은 칸은 모든 게시판을 포함합니다. 인기글은 코어 인기글 캐시에서 고르므로 게시판을 좁게 고르면 설정 개수보다 적게 나올 수
  있습니다.
- 검색엔진 봇 화면에도 같은 데이터가 서버에서 그려집니다(`core.seo.filter_context`, 티커는 정적 목록).
- 데이터는 `GET /api/plugins/g7-home-widgets/home` 한 번으로 받습니다(공개, `optional.sanctum`).

## 관리자 화면 — 홈 화면 설정

**관리자 → 홈 화면 설정** (`/admin/plugins/g7-home-widgets/settings`, 0.4.0 전 이름 "홈 위젯 게시판 설정")

- **[위젯 레이아웃] 탭**: 왼쪽에 섹션 1~5 상자(현재 설정대로 — 꺼짐은 흐리게, 2단은 좌우로), 오른쪽 줄에서
  섹션마다 켜기와 단 수를 정합니다. 줄에 마우스를 올리거나 줄을 누르면 해당 상자가 강조되고, 바꾼 값은
  저장 전에도 상자에 바로 반영됩니다.
- **[1번 섹션]~[5번 섹션] 탭**: 칸마다 위젯 종류 → 제목·아이콘 → 출력 개수(1~20) → 게시판 선택(또는 티커
  게시판 1개, HTML 입력). 2단 섹션은 [왼쪽 칸 · 종류] [오른쪽 칸 · 종류] 하위 탭으로 한 칸씩 봅니다.
  - 아이콘은 격자 선택기(검색, 8열, 이름 툴팁)에서 고릅니다. 첫 칸은 종류별 기본 아이콘이고, 허용 목록은
    템플릿 아이콘 서브셋 141개(`resources/home/icons.json`)입니다.
  - 게시판은 "포함 / 포함 안 함" 카드(손잡이·이름·slug)를 끌거나 X·+ 버튼으로 옮깁니다. [전체 선택]·[전체 해제]는
    검색어가 있으면 검색된 게시판에만 적용됩니다. "포함 안 함" 목록만 저장합니다.
  - **표시 여부만 정하며 열람 권한과는 무관합니다.** 권한은 요청마다 코어가 판정합니다.
- 저장은 코어 플러그인 설정 저장 경로를 씁니다. 플러그인은 검증 규칙(개수 1~20, 티커 게시판 1개)을 더하고
  폼을 `home_layout` 으로 바꿔 저장하며, 저장 뒤 위젯 데이터 캐시와 봇 홈 캐시를 비웁니다.
- 새로 여는 칸(1단 섹션을 2단으로 바꿀 때 생기는 칸 포함)은 게시판이 모두 "포함"인 상태로 열립니다.
- 읽기 API: `GET admin/home-form`, `GET admin/home-meta` (`core.plugins.read`).

## 웹진 애드온 연동 (g7-webzine-addon)

- 웹진 스타일 위젯은 [g7-webzine-addon](https://github.com/William1607cho/g7-webzine-addon) 1.3.0 이상의
  **공개 계약** `Plugins\G7\Webzine\Addon\PublicApi\WebzineCards::cards()`(`VERSION >= 1`)로 요약·썸네일만
  받습니다. 애드온 내부 클래스는 쓰지 않습니다. 글 목록은 갤러리와 같은 코어 경로로 얻습니다.
- 비밀글은 항상 제목과 자물쇠만 보이고 요약은 없습니다. 썸네일 없는 일반 글은 애드온 설정의 대체 이미지가
  있으면 그것을, 없으면 대체 틀을 보여 줍니다.
- 애드온 사용 가능 = 코어가 플러그인을 활성으로 보고 **그리고** 계약 클래스·판본이 있을 때. 요청마다 한 번만
  판정하므로 애드온을 켜고 끈 직후 다음 요청부터 반영됩니다. 애드온을 켜거나 끄면 위젯 데이터 캐시와 봇 홈
  캐시도 비웁니다(코어는 활성화 때만 SEO 캐시를 비웁니다).
- **애드온이 없을 때**(설치 안 됨, 비활성, 계약이 없는 1.2.0 이하)
  - 관리자 화면: 웹진 선택지는 "(애드온 필요)"가 붙은 비활성 선택지입니다. 종류가 웹진인 칸에는 미리보기
    이미지(`resources/assets/webzine-preview.webp` — 같은 이름으로 파일을 바꾸면 교체됩니다)·안내·저장소
    링크가 보입니다. 웹진으로 저장하는 것은 막지 않습니다(설정 보존).
  - 홈 화면: 웹진 칸은 **최근글로 대신** 그립니다(브라우저·봇 모두). 그 칸의 제목·아이콘·개수·게시판 선택은
    그대로 쓰고, 제목·아이콘을 비워 뒀으면 최근글 기본값을 씁니다. 설정은 바뀌지 않으므로 애드온이 돌아오면
    다시 웹진으로 보입니다.

## 이미지 전송 연동 (g7-image-delivery, 0.4.2)

- 갤러리·웹진 칸의 썸네일은 [g7-image-delivery](https://github.com/William1607cho/g7-image-delivery) 0.3.0
  이상의 **공개 계약** `Plugins\G7\Image\Delivery\PublicApi\ThumbnailVariants::lookup()`(`VERSION >= 1`)으로
  변환본을 찾아 씁니다. 플러그인 내부 클래스·표는 읽지 않습니다. 필수 의존이 아닙니다.
- 한 응답의 모든 갤러리·웹진 썸네일을 모아 **한 번만** 조회합니다(칸·글마다 조회하지 않음). 위젯 목록 캐시
  뒤에서 요청마다 하므로, 변환본이 생기면 다음 요청부터 바로 쓰입니다.
- `src` 는 240 변환본, `srcset` 은 240·960(있는 것), `sizes` 는 갤러리 `(min-width: 640px) 200px, 50vw`,
  웹진 `(max-width: 639px) 64px, 80px`. 원본이 960 이하라 960 변환본이 없으면 JPEG·WebP 원본을 제 폭으로
  후보에 더합니다(고밀도 화면에서 240 을 늘려 그리지 않게). PNG 원본은 넣지 않습니다.
- 변환본이 아직 없는 글(g7-image-delivery 스케줄러가 10분마다 만듭니다)과, 플러그인이 없거나 꺼져 있거나 계약이
  없는 옛 판일 때는 **원본 주소를 그대로** 씁니다 — 게시판 목록과 같은 동작이고, 0.4.1 과 같습니다.
- 비밀글은 조회하지 않고(자물쇠), 웹진 애드온 대체 이미지는 애드온 값(240 파생본) 그대로입니다.
- 봇 화면도 같은 데이터를 쓰므로 `src` 가 240 변환본입니다. 템플릿의 봇 렌더 허용 속성에 `srcset`·`sizes` 가
  없어 봇 HTML 에는 `src` 만 나갑니다.

## 0.5.0 변경 안내 (다른 사이트 운영자용)

0.4.x 까지 남겨 두었던 옛 기능을 뺐습니다. 홈 섹션(`GET /api/plugins/g7-home-widgets/home`)의 응답과
관리자 화면은 아래 "저장 안 된 칸" 한 가지 외에는 그대로입니다.

- **없어진 API**(모두 404 가 됩니다)
  - `GET /api/plugins/g7-home-widgets/recent-posts`
  - `GET /api/plugins/g7-home-widgets/popular-posts`
  - `GET /api/plugins/g7-home-widgets/notice-posts`
  - `GET` · `PUT /api/plugins/g7-home-widgets/admin/board-filter`

  템플릿이나 다른 확장이 이 주소를 데이터 소스로 부르고 있으면 업데이트 전에 지우세요. 같은 목록은 홈 섹션의
  최근글·인기글·뉴스 티커 위젯이 보여 줍니다. `g7-wc-community` 는 fork-20260926 부터 이 주소를 부르지 않습니다.
- **공통 제외 설정 `excluded_board_ids` 는 더 이상 읽지 않습니다.** 설정 파일에 저장된 값은 지우지 않고 그대로
  두며(되돌릴 때 그대로 쓰입니다), 설정 화면·설정 저장 API 도 이 키를 받지 않습니다(요청에 섞여 와도 무시).
- **저장 안 된 칸의 기본값**: 0.4.x 에서는 게시판 선택을 저장하지 않은 목록 위젯 칸이 공통 제외 목록을 "포함 안
  함" 기본값으로 썼습니다. 0.5.0 에서는 그런 칸이 **모든 게시판을 포함**합니다. 홈 화면 설정을 한 번이라도
  저장했다면 보이는 칸은 모두 자기 선택을 갖고 있어 바뀌지 않습니다. 나중에 1단 섹션을 2단으로 바꾸면 새 칸은
  "모두 포함"으로 열리므로, 빼고 싶은 게시판을 그 칸에서 직접 고르세요.
- 확인 방법: 설정 파일(`storage/app/plugins/g7-home-widgets/settings/setting.json`)의 `home_layout` 에서 보이는
  칸마다 `boards` 가 있는지 보면 됩니다. 없거나 `null` 인 칸은 0.5.0 에서 모든 게시판을 보여 줍니다.

## 요구 사항

- 그누보드7 `>= 7.0.10`
- `sirsoft-board` 모듈 `>= 1.1.2`
- 선택: 웹진 스타일 위젯을 쓰려면 g7-webzine-addon `>= 1.3.0`

## 설치·업데이트

```bash
# 이 저장소를 plugins/g7-home-widgets 에 두고:
php artisan plugin:install g7-home-widgets
php artisan plugin:activate g7-home-widgets
```

또는 **관리자 → 플러그인**에서 설치·활성화합니다.

- 이전 버전에서 업데이트하면 홈 섹션은 기본값(0.3.0 홈 모양)으로 바로 나타납니다. 설정 파일은 고쳐 쓰지 않습니다.
- 업데이트 뒤 훅 리스너가 한 번 늦게 반영될 수 있습니다(업데이트 명령 안에서 옛 플러그인 클래스가 먼저 올라와
  있기 때문). 같은 패키지로 `plugin:update` 를 한 번 더 하면 확실합니다. 관리자 메뉴 이름("홈 화면 설정")도
  이때 반영됩니다. 0.4.x → 0.5.0 은 훅 리스너 구성이 같아 한 번이면 됩니다.
- 설정 화면이 안 보이면 `php artisan plugin:refresh-layout g7-home-widgets` 로 레이아웃을 새로 등록합니다.
- 라우트를 캐시하는 사이트에서 새 라우트가 안 보이면:

```bash
php artisan plugin:cache-clear
php artisan route:clear && php artisan route:cache
```

## 되돌리기(원복)

- **0.5.0 → 0.4.2**: 0.4.2 패키지로 `plugin:update` 하면 없어졌던 API 가 다시 생기고, 설정 파일에 남아 있던
  `excluded_board_ids` 값을 0.4.2 가 다시 읽어 업데이트 전과 같게 동작합니다(0.5.0 은 그 값을 지우지 않습니다).
- **0.4.x → 0.3.0**: 홈 섹션이 사라지고 템플릿 홈 위젯만 남습니다. 홈 overlay 레이아웃 확장 행은 DB 에 남아 홈
  방문마다 없어진 `/api/plugins/g7-home-widgets/home` 을 한 번 더 부르고 404 가 납니다(화면·토스트 변화 없음).
  설정 파일의 `home_layout` 키는 남고(0.3.0 은 읽지 않음), 다시 올리면 그대로 쓰입니다.
- g7-webzine-addon 을 1.2.0 이하로 되돌리면 공개 계약이 없어지므로 홈의 웹진 칸은 최근글로 대신 보입니다(오류 없음).

## 제거

DB 테이블은 없습니다. 설정(`home_layout`, 0.4.x 에서 남은 값이 있으면 그것도)은 플러그인 설정 파일에 있습니다.
**관리자 → 플러그인**에서 비활성화·제거하거나 `php artisan plugin:uninstall g7-home-widgets` 를 쓰고, 템플릿에서
이 플러그인 주소를 부르는 데이터 소스를 지우세요. 홈 overlay 레이아웃 확장 행은 제거 뒤에도 DB 에 남을 수
있습니다(그 경우 홈 방문마다 `/home` 404 요청 1회 — "되돌리기"의 0.3.0 항목과 같은 흔적).

## 라이선스

MIT — [LICENSE](./LICENSE) 참고.

## English summary

A Gnuboard7 plugin for home pages built on `sirsoft-board`.

- **Home sections**: five sections (each on/off, one or two columns); each column shows recent
  posts, popular posts, a news ticker, a gallery, a webzine-style list (needs
  [g7-webzine-addon](https://github.com/William1607cho/g7-webzine-addon) 1.3.0+) or admin-written
  HTML. Set it in **Admin → Home Page Settings**: per-column widget type, title, icon, item count
  (1–20) and board choice. Bot (SSR) pages show the same sections. Post lists, read permission and
  secret posts are left to `sirsoft-board`; template files are not modified.
- Without the webzine add-on, webzine columns are drawn as recent posts (keeping their title, icon,
  count and boards) and the admin screen marks the type "(add-on required)".
- **0.5.0 removes legacy parts**: the `recent-posts`, `popular-posts` and `notice-posts` APIs and the
  `admin/board-filter` API now return 404, and the `excluded_board_ids` setting is no longer read (the
  stored value is left untouched, so rolling back to 0.4.2 restores the old behaviour). A column with
  no saved board choice now includes every board; columns you have saved keep their own choice.
- **Updating**: 0.4.x → 0.5.0 needs one `plugin:update` (hook listeners are unchanged). Earlier
  upgrades that added listeners needed a second run with the same package.
- **Rolling back to 0.3.0** removes the sections; the home overlay row stays, so each home visit makes
  one extra request that returns 404 (no visible change).
