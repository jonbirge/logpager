# TODO

- Searches should obey exclusions to match heatmap
- Generate new heatmap for searches
- Multiple field searches should AND together
- Unified function to handle searches and paging, called by heatmap and front-end, that returns JSON array?
- Search interface should specify fields as parameter
- Allow heatmap.php to filter with search term (which would happen automatically if the above were implemented)
- Exclusion function should move from hardwired list to loading from external JSON file or CSV
- External PHP function to create table header array? May not be neccesary if refactor puts this info in only one unified table generation function.
