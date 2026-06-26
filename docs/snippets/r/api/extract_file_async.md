```r title="R"
library(xberg)

# extract is the async variant; extendr drives the tokio runtime so the
# call returns once extraction completes. R has no native async, so wrap with
# the future/promises packages if non-blocking dispatch is required.
json <- extract(
  path = "document.pdf",
  mime_type = "application/pdf",
  config = ExtractionConfig$default()
)
result <- jsonlite::fromJSON(json, simplifyVector = FALSE)

cat(sprintf("Extracted %d characters from %s\n", nchar(result$content), result$mime_type))
```
