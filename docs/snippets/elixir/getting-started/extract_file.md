```elixir title="Elixir"
defmodule Extract do
  def show_extraction_details do
    # Extract from a file
    case Xberg.extract_sync("document.pdf", nil, nil) do
      {:ok, result} ->
        # Result is a string containing extracted content
        IO.puts("Content length: #{String.length(result)} characters")
        IO.puts("---")
        IO.puts(result)
        :ok

      {:error, reason} ->
        IO.puts("Failed to extract: #{reason}")
        :error
    end
  end
end
```
