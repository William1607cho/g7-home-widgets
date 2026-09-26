# 홈 화면 설정 — 안내 이미지 자리

관리자 "홈 화면 설정" 화면 왼쪽(3) 안내 영역에 보일 그림을 이 폴더에 둔다. 탭마다 1개.

| 탭 | 파일 이름 |
|---|---|
| 위젯 레이아웃 | `layout.webp` |
| 1번 섹션 ~ 5번 섹션 | `section-1.webp` ~ `section-5.webp` |

- 형식: `webp` 권장. `png`·`jpg` 도 된다(같은 이름이면 webp → png → jpg 순으로 먼저 찾은 것).
- 권장 크기: 가로 800px × 세로 600px(4:3). 자리표시 틀도 4:3 이다. 파일당 200KB 이하 권장.
- 파일이 없으면 자리표시 틀("안내 이미지 자리")이 보인다. 코드·설정 변경은 필요 없다.
- 주소: `/api/plugins/assets/g7-home-widgets/resources/assets/admin-guide/<이름>?v=<수정 시각>`
  (`GET /api/plugins/g7-home-widgets/admin/home-meta` 의 `guides` 가 준다).
- 플러그인 파일이므로 저장소에 넣고 릴리스(태그 zip)로 반영한다. 설치 폴더에 직접 넣으면 다음 업데이트 때 지워진다.
