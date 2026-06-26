```elixir title="Elixir"
defmodule Example do
  def extract do
    config = nil

    case Xberg.extract_sync("document.pdf", nil, config) do
      {:ok, result} ->
        IO.puts("Content: #{result}")
        :ok

      {:error, reason} ->
        IO.puts("Error: #{reason}")
        :error
    end
  end
end
```
