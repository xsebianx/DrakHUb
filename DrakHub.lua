local HttpService = game:GetService("HttpService")
local hwid = game:GetService("RbxAnalyticsService"):GetClientId()
local url = "https://46191531-d1f8-4e08-a0ac-5b0e1aa22935-00-296luuro2wrme.picard.replit.dev/?hwid=" .. hwid

local success, resultado = pcall(function()
    return HttpService:GetAsync(url)
end)

if success and resultado and resultado ~= "no_autorizado" then
    local func, err = loadstring(resultado)
    if func then
        func()
    else
        warn("Error al cargar el menú: " .. tostring(err))
    end
else
    warn("No autorizado para usar el menú")
end
